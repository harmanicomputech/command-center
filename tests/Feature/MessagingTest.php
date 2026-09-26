<?php

namespace Tests\Feature;

use App\Models\AiCall;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\MessageDraft;
use App\Models\PolicyDocument;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\AnthropicModel;
use App\Services\Ai\Claude;
use App\Services\Ai\LanguageModel;
use App\Services\Ai\Prompts;
use App\Services\Ai\PushNextSuggester;
use App\Services\Ai\Scrub;
use App\Support\Secrets;
use App\Support\Settings;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

/**
 * Phase 7: AI drafting with the policy brief, approval, cost logging and
 * the daily suggestion. Claude itself is faked; one test checks the exact
 * request the SDK sends.
 */
class MessagingTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    private object $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();

        $this->model = new class implements LanguageModel
        {
            public array $calls = [];

            public array $answer = ['variants' => [['text' => 'Izzi farmers: fertiliser at every ward by March. Town hall Saturday.', 'angle' => 'Local fix'], ['text' => 'Version two', 'angle' => 'Promise'], ['text' => 'Version three', 'angle' => 'Call to act']]];

            public ?AiException $fail = null;

            public function json(array $system, string $prompt, array $schema, int $maxTokens): array
            {
                $this->calls[] = compact('system', 'prompt', 'schema', 'maxTokens');
                if ($this->fail) {
                    throw $this->fail;
                }

                return ['data' => $this->answer, 'model' => 'claude-opus-5', 'usage' => ['input' => 1000, 'output' => 400, 'cache_read' => 5000, 'cache_write' => 0]];
            }
        };
        $this->app->instance(LanguageModel::class, $this->model);
    }

    public function test_a_draft_uses_the_cached_policy_brief_and_segment_counts_never_personal_data(): void
    {
        Secrets::set('anthropic_api_key', 'sk-ant-test-key-0000');
        Settings::set('campaign.candidate', 'Test Candidate');
        PolicyDocument::create(['topic' => 'agriculture', 'title' => 'Fertiliser plan', 'body' => 'Fertiliser depots in every ward by March, with mechanisation hubs per LGA.', 'active' => true]);
        PolicyDocument::create(['topic' => 'roads', 'title' => 'Old plan', 'body' => 'An inactive brief that should not be sent.', 'active' => false]);

        $izzi = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($izzi)->create();
        $this->syncVoters($agent, [$this->voterPayload($izzi->id, ['name' => 'Ngozi Secretname', 'phone' => '0803 555 0101', 'occupation' => 'farmer'])])->assertOk();
        Issue::create(['uuid' => (string) Str::uuid(), 'category' => 'road', 'description' => 'Chief Somebody says the road is cut', 'severity' => 'high', 'lga_id' => $izzi->lga_id, 'ward_id' => $izzi->id, 'community' => 'Onu', 'reported_by' => $agent->id, 'reported_at' => now()]);

        $strategist = User::factory()->strategist()->create();
        $this->actingAs($strategist)->get('/messages/new?filters[occupation][]=farmer&filters[lga_id][]='.$izzi->lga_id)
            ->assertOk()->assertSee('Occupation: Farmer');

        $response = $this->post('/messages', [
            'goal' => 'Invite farmers to the town hall, call 08031234567 or mail someone@example.com',
            'channel' => 'sms', 'language' => 'en', 'tone' => 'hopeful',
            'filters' => ['occupation' => ['farmer'], 'lga_id' => [$izzi->lga_id]],
        ]);

        $response->assertSessionHasNoErrors();
        $draft = MessageDraft::sole();
        $response->assertRedirect("/messages/{$draft->id}");
        $this->assertSame('ready', $draft->status);
        $this->assertCount(3, $draft->variants);
        $this->assertSame(1, $draft->audience_size);

        $call = $this->model->calls[0];
        // Stable blocks first; the cache breakpoint is on the policy brief.
        $this->assertTrue($call['system'][1]['cache']);
        $this->assertStringContainsString('Fertiliser depots in every ward', $call['system'][1]['text']);
        $this->assertStringNotContainsString('inactive brief', $call['system'][1]['text']);
        $this->assertStringContainsString('Test Candidate', $call['system'][0]['text']);
        $this->assertStringNotContainsString(now()->format('Y'), $call['system'][0]['text'].$call['system'][1]['text'], 'No dates in the cached prefix.');
        $this->assertSame($call['system'], Prompts::system(), 'The stable prefix is deterministic.');
        // The request has counts and places, never people.
        $this->assertStringContainsString('Occupation: Farmer', $call['prompt']);
        $this->assertStringContainsString('Bad road: 1 (Onu 1)', $call['prompt']);
        $this->assertStringNotContainsString('Secretname', $call['prompt']);
        $this->assertStringNotContainsString('Chief Somebody', $call['prompt']);
        $this->assertStringNotContainsString('0803', $call['prompt']);
        $this->assertStringNotContainsString('08031234567', $call['prompt']);
        $this->assertStringNotContainsString('someone@example.com', $call['prompt']);

        // Tokens and cost logged: 1000×5 + 400×25 + 5000×0.5 (cache read) = 17,500 µ$.
        $log = AiCall::sole();
        $this->assertSame('draft', $log->purpose);
        $this->assertEqualsWithDelta(0.0175, $log->cost_usd, 0.000001);
        $this->assertSame($strategist->id, $log->user_id);

        // A person approves (and may edit); their name is saved.
        $this->get("/messages/{$draft->id}")->assertOk()->assertSee('Three versions')->assertSee('Izzi farmers');
        $this->post("/messages/{$draft->id}/approve", ['chosen' => 0, 'final_text' => 'Izzi farmers: fertiliser in every ward by March.'])->assertRedirect();
        $draft->refresh();
        $this->assertSame('approved', $draft->status);
        $this->assertSame($strategist->id, $draft->approved_by);
        $this->assertNotNull($draft->approved_at);
        $this->assertTrue(AuditLog::query()->where('action', 'messages.approve')->where('description', 'like', '%(edited)%')->exists());
        $this->get("/messages/{$draft->id}")->assertSee('Approved by '.$strategist->name)->assertSee('Send as SMS broadcast');
    }

    public function test_drafting_needs_a_key_respects_the_budget_and_logs_failures(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/messages', ['goal' => 'Say hello to everyone', 'channel' => 'sms', 'language' => 'en'])
            ->assertSessionHas('error');
        $this->assertSame(0, MessageDraft::query()->count());

        Secrets::set('anthropic_api_key', 'sk-ant-test');
        Settings::set('ai.monthly_budget', '1');
        AiCall::create(['purpose' => 'draft', 'model' => 'claude-opus-5', 'status' => 'ok', 'cost_usd' => 1.25, 'created_at' => now()]);

        $this->post('/messages', ['goal' => 'Say hello to everyone', 'channel' => 'sms', 'language' => 'ig']);
        $draft = MessageDraft::sole();
        $this->assertSame('failed', $draft->status);
        $this->assertStringContainsString('budget', $draft->error);
        $this->assertCount(0, $this->model->calls);

        Settings::set('ai.monthly_budget', '0');
        $this->model->fail = new AiException('Claude is busy (rate limit). Try again in a minute.');
        $this->post("/messages/{$draft->id}/again")->assertRedirect();
        $this->assertSame('failed', MessageDraft::query()->latest('id')->first()->status);
        $this->assertSame('error', AiCall::query()->latest('id')->first()->status);

        // Leaders who aren't analysts can't use the engine.
        $leader = User::factory()->lgaLeader($this->lga('Izzi')->id)->create();
        $this->actingAs($leader)->get('/messages')->assertForbidden();
    }

    public function test_the_sdk_request_has_caching_structured_output_and_fallbacks(): void
    {
        Secrets::set('anthropic_api_key', 'sk-ant-test-key');
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json', 'request-id' => 'req_1'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5',
            'content' => [['type' => 'text', 'text' => json_encode(['variants' => [['text' => 'Hello Ebonyi', 'angle' => 'Warm']]])]],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 120, 'output_tokens' => 30, 'cache_read_input_tokens' => 3000, 'cache_creation_input_tokens' => 0],
        ]))]));
        $stack->push(Middleware::history($history));

        $answer = (new AnthropicModel(new Guzzle(['handler' => $stack])))->json(
            [['text' => 'Rules'], ['text' => 'Policy brief', 'cache' => true]], 'Write 3 variants', ['type' => 'object'], 16000,
        );

        $this->assertSame('Hello Ebonyi', $answer['data']['variants'][0]['text']);
        $this->assertSame(['input' => 120, 'output' => 30, 'cache_read' => 3000, 'cache_write' => 0], $answer['usage']);

        $request = $history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('claude-opus-5', $body['model']);
        $this->assertSame(['type' => 'adaptive'], $body['thinking']);
        $this->assertSame(['type' => 'ephemeral'], $body['system'][1]['cache_control']);
        $this->assertArrayNotHasKey('cache_control', $body['system'][0]);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $this->assertSame('sk-ant-test-key', $request->getHeaderLine('x-api-key'));
    }

    public function test_the_daily_suggestion_is_written_for_the_dashboard(): void
    {
        $this->assertNull(app(PushNextSuggester::class)->run(), 'Nothing happens until AI is set up.');

        Secrets::set('anthropic_api_key', 'sk-ant-test');
        $this->model->answer = ['suggestion' => 'Push the fertiliser message in Izzi this week: road complaints are rising there.'];
        $this->artisan('ai:suggest')->assertSuccessful();

        $this->assertSame('Push the fertiliser message in Izzi this week: road complaints are rising there.', Settings::get('brief.suggestion'));
        $this->assertStringContainsString('Wards by zone', $this->model->calls[0]['prompt']);
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee('AI suggestion, not a decision')->assertSee('fertiliser message in Izzi');
        $this->assertSame('suggestion', AiCall::sole()->purpose);
    }

    public function test_policy_briefs_are_managed_in_the_app(): void
    {
        $this->actingAs(User::factory()->strategist()->create());
        $this->post('/policies', ['topic' => 'water', 'title' => 'Boreholes', 'body' => 'A borehole for every community without clean water within two years.', 'active' => '1'])->assertRedirect('/policies');
        $policy = PolicyDocument::sole();
        $this->get('/policies')->assertOk()->assertSee('Boreholes');
        $this->put("/policies/{$policy->id}", ['topic' => 'water', 'title' => 'Boreholes', 'body' => 'A borehole for every community without clean water within two years.'])->assertRedirect();
        $this->assertFalse($policy->refresh()->active);
        $this->assertStringContainsString('has not written its policy brief', Prompts::knowledgeBase());
    }

    public function test_scrub_removes_numbers_and_emails(): void
    {
        $this->assertSame('Call [number removed] or [email removed] today', Scrub::text('Call +234 803 123 4567 or ada@example.com today'));
        $this->assertSame('Ward 01 had 14 reports', Scrub::text('Ward 01 had 14 reports'));
        $this->assertEqualsWithDelta(0.0175, Claude::cost('claude-opus-5', ['input' => 1000, 'output' => 400, 'cache_read' => 5000, 'cache_write' => 0]), 1e-9);
    }
}
