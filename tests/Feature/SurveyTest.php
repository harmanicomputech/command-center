<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Intelligence;
use App\Services\Points;
use App\Support\Settings;
use App\Support\SurveyPoll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class SurveyTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    private function makeSurvey(array $channels = ['field', 'web', 'sms'], ?array $lgaIds = null): Survey
    {
        $this->actingAs(User::factory()->strategist()->create());
        $questions = [
            ['type' => 'intention', 'prompt' => 'Who would you vote for?', 'options' => [], 'required' => true],
            ['type' => 'single', 'prompt' => 'Best time to meet?', 'options' => ['Morning', 'Evening', ''], 'required' => true],
            ['type' => 'text', 'prompt' => 'Anything else?', 'options' => [], 'required' => false],
        ];
        $this->post('/surveys', ['title' => 'Izzi pulse', 'channels' => $channels, 'lga_ids' => $lgaIds ?? [$this->lga('Izzi')->id], 'quota_per_ward' => 2, 'sms_keyword' => 'pulse', 'questions' => json_encode($questions)])->assertRedirect();

        return Survey::latest('id')->firstOrFail();
    }

    public function test_surveys_are_built_launched_and_only_drafts_change(): void
    {
        $this->actingAs(User::factory()->strategist()->create());
        $this->post('/surveys', ['title' => 'Bad', 'channels' => ['field'], 'questions' => json_encode([['type' => 'single', 'prompt' => 'Only one choice', 'options' => ['A']]])])->assertSessionHasErrors('questions');

        $survey = $this->makeSurvey();
        $this->assertSame(['draft', 'PULSE', 3], [$survey->status, $survey->sms_keyword, $survey->questions()->count()]);
        $this->assertSame(['Morning', 'Evening'], $survey->questions[1]->options, 'Empty choices are dropped.');

        $this->get("/surveys/{$survey->id}/edit")->assertOk();
        $this->post("/surveys/{$survey->id}/status", ['status' => 'live'])->assertSessionHas('status');
        $this->get("/surveys/{$survey->id}/edit")->assertForbidden();
        $this->get('/surveys')->assertOk()->assertSee('Izzi pulse');

        $this->actingAs(User::factory()->coordinator($this->ward('Izzi Ward 01'))->create());
        $this->get('/surveys/create')->assertForbidden();
    }

    public function test_agents_run_surveys_offline_and_earn_points(): void
    {
        $survey = $this->makeSurvey();
        $survey->update(['status' => 'live']);
        $q = $survey->questions;
        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        $outsider = User::factory()->agent($this->ward('Abakaliki Ward 01'))->create();

        $this->actingAs($agent)->get('/field/surveys')->assertOk()->assertSee('Izzi pulse');
        $this->get("/field/surveys/{$survey->id}")->assertOk()->assertSee('Who would you vote for?');
        $this->actingAs($outsider)->get("/field/surveys/{$survey->id}")->assertNotFound();

        $send = fn (User $user, array $answers, array $extra = []) => $this->actingAs($user)->postJson('/api/field/sync', ['items' => [['id' => (string) Str::uuid(), 'type' => 'survey_response', 'payload' => ['survey_id' => $survey->id, 'answers' => $answers, ...$extra]]]]);

        $send($agent, [$q[0]->id => 'definitely_us'])->assertJsonPath('results.0.status', 'invalid');
        $send($agent, [$q[0]->id => 'nonsense', $q[1]->id => '1'])->assertJsonPath('results.0.status', 'invalid');
        $send($agent, [$q[0]->id => 'definitely_us', $q[1]->id => '2'], ['phone' => '0800 000 8001', 'age_band' => '25-34'])->assertJsonPath('results.0.status', 'ok');
        $send($agent, [$q[0]->id => 'undecided', $q[1]->id => '1'], ['phone' => '+234 800 000 8001'])->assertJsonPath('results.0.status', 'invalid')->assertJsonPath('results.0.message', 'This phone number has already answered this survey.');
        $send($outsider, [$q[0]->id => 'undecided', $q[1]->id => '1'])->assertJsonPath('results.0.status', 'invalid');

        $response = SurveyResponse::sole();
        $this->assertSame(['field', $this->ward('Izzi Ward 01')->id, '25-34', $agent->id], [$response->channel, $response->ward_id, $response->age_band, $response->collected_by]);
        $this->assertNotNull($response->phone_hash);
        $this->assertSame(2, app(Points::class)->totals([$agent->id])[$agent->id]['points']);
    }

    public function test_results_show_the_sample_size_and_break_down_by_group(): void
    {
        $survey = $this->makeSurvey();
        $survey->update(['status' => 'live']);
        $q = $survey->questions;
        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        foreach (['definitely_us', 'definitely_us', 'undecided'] as $i => $answer) {
            $this->actingAs($agent)->postJson('/api/field/sync', ['items' => [['id' => (string) Str::uuid(), 'type' => 'survey_response', 'payload' => ['survey_id' => $survey->id, 'answers' => [$q[0]->id => $answer, $q[1]->id => '1'], 'occupation' => 'farmer']]]]);
        }

        $this->actingAs(User::factory()->strategist()->create());
        $this->get("/surveys/{$survey->id}")->assertOk()->assertSee('n = 3')->assertSee('Small sample')->assertSee('67%')->assertSee('Izzi Ward 01');
        $this->get("/surveys/{$survey->id}?by=occupation&question={$q[0]->id}")->assertOk()->assertSee('Farmer');

        $this->actingAs(User::factory()->admin()->create());
        $csv = $this->get("/surveys/{$survey->id}/export")->assertOk()->streamedContent();
        $this->assertStringContainsString('Definitely our candidate', $csv);
        $this->assertStringNotContainsString('0800', $csv);
    }

    public function test_voting_intention_feeds_the_ward_zone(): void
    {
        Settings::set('intel.min_sample', '3');
        $survey = $this->makeSurvey();
        $survey->update(['status' => 'live']);
        $q = $survey->questions;
        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        foreach (['definitely_other', 'definitely_other', 'probably_other'] as $answer) {
            $this->actingAs($agent)->postJson('/api/field/sync', ['items' => [['id' => (string) Str::uuid(), 'type' => 'survey_response', 'payload' => ['survey_id' => $survey->id, 'answers' => [$q[0]->id => $answer, $q[1]->id => '1']]]]]);
        }

        $row = app(Intelligence::class)->wards()->firstWhere('name', 'Izzi Ward 01');
        $this->assertSame(['weak', 8.3, ['survey']], [$row['zone'], $row['share'], $row['sources']]);
        $this->assertSame('Based on 3 survey responses', $row['basis']);
    }

    public function test_the_public_web_link(): void
    {
        $survey = $this->makeSurvey();
        $q = $survey->questions;
        auth()->logout();

        $this->get("/s/{$survey->web_token}")->assertOk()->assertSee('This survey is closed');
        $survey->update(['status' => 'live']);
        $this->get("/s/{$survey->web_token}")->assertOk()->assertSee('Who would you vote for?')->assertSee('Izzi Ward 01')->assertDontSee('Abakaliki Ward 01');

        $this->post("/s/{$survey->web_token}", ['answers' => [$q[0]->id => 'probably_us']])->assertSessionHasErrors();
        $this->post("/s/{$survey->web_token}", ['answers' => [$q[0]->id => 'probably_us', $q[1]->id => '2'], 'ward_id' => $this->ward('Izzi Ward 02')->id, 'phone' => '0800 000 8002'])->assertRedirect()->assertCookie('survey_'.$survey->id, 'done');
        $this->post("/s/{$survey->web_token}", ['answers' => [$q[0]->id => 'undecided', $q[1]->id => '2'], 'phone' => '08000008002'])->assertSessionHasErrors('phone');
        $this->post("/s/{$survey->web_token}", ['answers' => [$q[0]->id => 'undecided', $q[1]->id => '2'], 'website' => 'spam'])->assertRedirect();
        $this->assertSame(1, SurveyResponse::count());
        $this->assertSame('web', SurveyResponse::sole()->channel);
    }

    public function test_ussd_and_sms_polls(): void
    {
        $survey = $this->makeSurvey(['sms']);
        $survey->update(['status' => 'live']);
        $token = SurveyPoll::token();
        $ussd = fn (string $text, string $phone = '+2348000009001') => $this->post("/api/poll/ussd/{$token}", ['sessionId' => 's1', 'phoneNumber' => $phone, 'text' => $text]);

        $ussd('')->assertSee('END No poll is running');
        $this->post("/surveys/{$survey->id}/ussd")->assertSessionHas('status');
        $this->post('/api/poll/ussd/wrong', ['text' => ''])->assertNotFound();

        $first = $ussd('')->assertOk()->getContent();
        $this->assertStringStartsWith('CON ', $first);
        $this->assertStringContainsString('1. Definitely our candidate', $first);
        $this->assertLessThanOrEqual(186, mb_strlen($first));

        $ussd('9')->assertSee('Choose a number from the list.');
        $ussd('9*1')->assertSee('Best time to meet?');
        $ussd('9*1*2')->assertSee('END Thank you! Your answers are saved.');
        $this->assertSame(['definitely_us', '2'], array_values(SurveyResponse::sole()->answers));
        $ussd('')->assertSee('END You have already answered');

        // SMS: "PULSE 3" answers the first question with choice 3.
        $this->post("/api/poll/sms/{$token}", ['from' => '+2348000009002', 'text' => 'pulse 3'])->assertOk();
        $this->post("/api/poll/sms/{$token}", ['from' => '+2348000009002', 'text' => 'PULSE 1'])->assertOk();
        $this->assertSame(2, SurveyResponse::count());
        $this->assertSame('undecided', SurveyResponse::where('channel', 'sms')->sole()->answers[(string) $survey->questions[0]->id]);
    }
}
