<?php

namespace Tests\Feature;

use App\Models\Narrative;
use App\Models\NarrativeReport;
use App\Models\NewsFeed;
use App\Models\NewsItem;
use App\Models\PagePost;
use App\Models\User;
use App\Services\Ai\LanguageModel;
use App\Services\Ai\NarrativeClusterer;
use App\Services\NewsTracker;
use App\Services\PushAlerts;
use App\Services\PushNotifier;
use App\Support\Secrets;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class NarrativesAndNewsTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PushAlerts::reset();
        $this->loadSmallRegister();
    }

    protected function tearDown(): void
    {
        PushAlerts::reset();
        parent::tearDown();
    }

    private function report(User $agent, int $wardId, array $overrides = []): string
    {
        $uuid = (string) Str::uuid();
        $this->actingAs($agent)->postJson('/api/field/sync', ['items' => [['id' => $uuid, 'type' => 'narrative_report', 'payload' => [
            'summary' => 'People say the market levy will double', 'source' => 'market', 'tone' => 'negative', 'topic' => 'markets', 'ward_id' => $wardId, 'seen_at' => now()->toIso8601String(), ...$overrides,
        ]]]])->assertOk()->assertJsonPath('results.0.status', 'ok');

        return $uuid;
    }

    public function test_agents_report_what_people_say_and_leaders_group_it_into_narratives(): void
    {
        $izzi = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($izzi)->create();
        $this->actingAs($agent)->get('/field/narratives')->assertOk()->assertSee('What are people saying?');

        $uuid = $this->report($agent, $izzi->id);
        $this->report($agent, $izzi->id);
        $this->actingAs($agent)->postJson('/api/field/sync', ['items' => [['id' => (string) Str::uuid(), 'type' => 'narrative_report', 'payload' => ['summary' => 'x', 'source' => 'radio', 'tone' => 'bad', 'topic' => 'markets', 'ward_id' => $izzi->id, 'link' => 'javascript:alert(1)']]]])
            ->assertJsonPath('results.0.status', 'invalid');
        $abakaliki = $this->ward('Abakaliki Ward 01');
        $this->report(User::factory()->agent($abakaliki)->create(), $abakaliki->id);
        $this->actingAs($agent);
        $this->assertSame(3, NarrativeReport::query()->count());
        $this->postJson('/api/field/sync', ['items' => [['id' => $uuid, 'type' => 'narrative_report', 'payload' => []]]])->assertJsonPath('results.0.status', 'ok');
        $this->assertSame(3, NarrativeReport::query()->count(), 'Syncing twice stores once.');

        // Screenshots follow the record, like issue photos.
        $this->post('/api/field/photos', ['uuid' => (string) Str::uuid(), 'owner_type' => 'narrative_report', 'owner_uuid' => $uuid, 'photo' => UploadedFile::fake()->image('shot.png', 400, 300)])->assertOk();

        $this->get('/narratives')->assertForbidden();

        // An Izzi leader sees Izzi's reports only.
        $leader = User::factory()->lgaLeader($izzi->lga_id)->create();
        $this->actingAs($leader)->get('/narratives')->assertOk()->assertSee('People say the market levy will double');
        $ids = NarrativeReport::query()->where('ward_id', $izzi->id)->pluck('id')->all();
        $other = NarrativeReport::query()->where('ward_id', '!=', $izzi->id)->value('id');

        $this->post('/narratives/group', ['report_ids' => [...$ids, $other], 'title' => 'Market levy rumour'])->assertRedirect();
        $narrative = Narrative::sole();
        $this->assertSame(['markets', 'negative', 'new'], [$narrative->topic, $narrative->tone, $narrative->status]);
        $this->assertSame(2, $narrative->reports()->count(), 'Reports outside the leader’s area are left alone.');
        $this->assertNull(NarrativeReport::query()->find($other)->narrative_id);

        $this->get("/narratives/{$narrative->id}")->assertOk()->assertSee('Market levy rumour')->assertSee('Screenshot for this report', false);
        $this->put("/narratives/{$narrative->id}", ['status' => 'responding'])->assertRedirect();
        $this->assertSame('responding', $narrative->refresh()->status);

        // Leaders elsewhere can't open it.
        $this->actingAs(User::factory()->lgaLeader($this->lga('Abakaliki')->id)->create())->get("/narratives/{$narrative->id}")->assertForbidden();
    }

    public function test_a_narrative_spiking_alerts_once_a_day(): void
    {
        $izzi = $this->ward('Izzi Ward 01');
        $admin = User::factory()->admin()->create();
        $narrative = Narrative::create(['title' => 'Levy rumour', 'topic' => 'markets', 'tone' => 'negative', 'status' => 'watching']);
        $notifier = new class extends PushNotifier
        {
            public array $calls = [];

            public function toTopic(string $topic, array $message, ?int $lgaId = null, ?array $userIds = null): int
            {
                $this->calls[] = ['topic' => $topic, 'message' => $message];

                return 1;
            }
        };
        $this->app->instance(PushNotifier::class, $notifier);

        foreach (range(1, 6) as $i) {
            $this->actingAs($admin)->post('/narratives/reports', ['summary' => "Heard it again ({$i})", 'source' => 'whatsapp', 'topic' => 'markets', 'tone' => 'negative', 'ward_id' => $izzi->id, 'narrative_id' => $narrative->id])->assertRedirect();
        }

        PushAlerts::flush();
        $alerts = collect($notifier->calls)->where('topic', 'narrative');
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Spiking: Levy rumour', $alerts->first()['message']['title']);
        $this->assertTrue($alerts->first()['message']['urgent']);
    }

    public function test_ai_grouping_suggestions_are_checked_and_accepted_by_a_person(): void
    {
        Secrets::set('anthropic_api_key', 'sk-ant-test');
        $izzi = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($izzi)->create(['name' => 'Agent Hiddenname']);
        $this->report($agent, $izzi->id, ['summary' => 'Levy will double, call 08031112222']);
        $this->report($agent, $izzi->id, ['summary' => 'The market levy is going up']);
        [$a, $b] = NarrativeReport::query()->orderBy('id')->pluck('id')->all();

        $model = new class($a, $b) implements LanguageModel
        {
            public array $prompts = [];

            public function __construct(private int $a, private int $b) {}

            public function json(array $system, string $prompt, array $schema, int $maxTokens): array
            {
                $this->prompts[] = $prompt;

                return ['data' => ['groups' => [
                    ['report_ids' => [$this->a, $this->b, 9999], 'narrative_id' => null, 'title' => 'Market levy increase', 'why' => 'Both about the levy'],
                    ['report_ids' => [$this->a], 'narrative_id' => 12345, 'title' => '', 'why' => 'Unknown narrative'],
                ]], 'model' => 'claude-opus-5', 'usage' => ['input' => 10, 'output' => 10, 'cache_read' => 0, 'cache_write' => 0]];
            }
        };
        $this->app->instance(LanguageModel::class, $model);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/narratives/suggest')->assertRedirect();

        $stored = NarrativeClusterer::stored();
        $this->assertSame('ready', $stored['status']);
        $this->assertCount(1, $stored['groups'], 'Unknown ids and narratives are dropped.');
        $this->assertSame([$a, $b], $stored['groups'][0]['report_ids']);
        $this->assertStringNotContainsString('Hiddenname', $model->prompts[0]);
        $this->assertStringNotContainsString('08031112222', $model->prompts[0]);

        $this->get('/narratives')->assertOk()->assertSee('AI suggestions: check before accepting')->assertSee('Market levy increase');
        $this->post('/narratives/group', ['report_ids' => [$a, $b], 'title' => 'Market levy increase'])->assertRedirect();
        $this->assertSame(2, Narrative::sole()->reports()->count());
        $this->assertSame([], NarrativeClusterer::stored()['groups']);
    }

    public function test_the_news_tracker_reads_rss_and_atom_and_flags_keywords(): void
    {
        Settings::set('news.keywords', 'Ebonyi, Test Opponent');
        Settings::set('campaign.candidate', 'Test Candidate');
        $rss = '<?xml version="1.0"?><rss version="2.0"><channel><title>News</title>'
            .'<item><title>Test Candidate visits Ebonyi farmers</title><link>https://news.example.com/a</link><guid>a1</guid><description>&lt;p&gt;Rice farmers met the candidate.&lt;/p&gt;</description><pubDate>'.now()->subHour()->toRfc2822String().'</pubDate></item>'
            .'<item><title>Football results</title><link>https://news.example.com/b</link><guid>b1</guid><pubDate>'.now()->subHours(2)->toRfc2822String().'</pubDate></item>'
            .'<item><title>Old story about Ebonyi</title><link>https://news.example.com/c</link><pubDate>'.now()->subYear()->toRfc2822String().'</pubDate></item>'
            .'</channel></rss>';
        $atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>Blog</title>'
            .'<entry><id>tag:blog,1</id><title>Test Opponent speaks in Abakaliki</title><link rel="alternate" href="https://blog.example.com/1"/><updated>'.now()->toAtomString().'</updated><summary>Speech</summary></entry>'
            .'</feed>';
        Http::fake(['news.example.com/*' => Http::response($rss), 'blog.example.com/*' => Http::response($atom), 'broken.example.com/*' => Http::response('not xml')]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/news/feeds', ['name' => 'News', 'url' => 'https://news.example.com/feed'])->assertSessionHas('status', 'Feed added: 2 stories.');
        NewsFeed::create(['name' => 'Blog', 'url' => 'https://blog.example.com/feed', 'active' => true]);
        NewsFeed::create(['name' => 'Broken', 'url' => 'https://broken.example.com/feed', 'active' => true]);

        $totals = app(NewsTracker::class)->fetchAll();
        $this->assertSame(['feeds' => 3, 'new' => 1, 'alerts' => 1], $totals);
        $this->assertSame(['Test Candidate', 'Ebonyi'], collect(NewsItem::query()->where('title', 'like', 'Test Candidate%')->value('keywords'))->sort()->values()->reverse()->values()->all());
        $this->assertSame('Rice farmers met the candidate.', NewsItem::query()->where('title', 'like', 'Test Candidate%')->value('summary'));
        $this->assertNull(NewsItem::query()->where('title', 'Football results')->value('keywords'));
        $this->assertFalse(NewsItem::query()->where('title', 'like', 'Old story%')->exists());
        $this->assertNotNull(NewsFeed::query()->where('name', 'Broken')->value('last_error'));
        $this->assertCount(1, collect(PushAlerts::pending())->where('topic', 'news'));

        $this->get('/news?alerts=1')->assertOk()->assertSee('Test Opponent speaks in Abakaliki')->assertDontSee('Football results');
        $item = NewsItem::query()->where('title', 'Football results')->first();
        $this->post("/news/{$item->id}/star")->assertRedirect();
        $this->assertTrue($item->refresh()->starred);

        $this->assertSame([], NewsTracker::match('Ebonyians celebrate', ['Ebonyi']), 'Whole words only.');
    }

    public function test_our_page_posts_are_logged_and_imported_from_csv(): void
    {
        $this->actingAs(User::factory()->strategist()->create());
        $this->post('/posts', ['platform' => 'facebook', 'posted_at' => '2026-10-01T10:00', 'text' => 'Rally in Afikpo', 'topic' => 'candidate', 'reach' => 5000, 'reactions' => 300, 'comments' => 40, 'shares' => 12])->assertRedirect();
        $this->assertSame('2026-10-01 09:00:00', PagePost::sole()->posted_at->format('Y-m-d H:i:s'), 'Form times are Lagos time.');

        $csv = "\u{FEFF}Publish time,Title,Permalink,Reach,Reactions,Comments,Shares\n"
            ."10/02/2026 18:00,Town hall in Izzi,https://facebook.example.com/p/1,\"12,000\",900,120,55\n"
            .",No date,,,,,\n";
        $file = UploadedFile::fake()->createWithContent('posts.csv', $csv);
        $this->post('/posts/import', ['file' => $file])->assertSessionHas('status', '1 posts imported, 1 rows skipped (no date or text).');
        $this->assertSame(12000, PagePost::query()->where('text', 'Town hall in Izzi')->value('reach'));
        $this->post('/posts/import', ['file' => UploadedFile::fake()->createWithContent('posts.csv', $csv)]);
        $this->assertSame(2, PagePost::query()->count(), 'Re-importing updates, not duplicates.');

        $this->get('/posts')->assertOk()->assertSee('Town hall in Izzi')->assertSee('What works');
    }

    public function test_the_engage_pages_render(): void
    {
        $izzi = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($izzi)->create();
        $this->report($agent, $izzi->id);

        $this->actingAs(User::factory()->admin()->create());
        foreach (['/messages', '/messages/new', '/policies', '/broadcasts', '/broadcasts/new', '/narratives', '/complaints', '/complaints?theme=markets', '/news', '/posts', '/system', '/settings', '/segments'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/complaints')->assertSee('Markets');
        $this->get('/system')->assertSee('Africa’s Talking callback URLs', false)->assertSee('AI usage this month');

        $this->post('/system/secrets', ['anthropic_api_key' => 'sk-ant-secret-1234'])->assertSessionHas('status');
        $this->assertSame('sk-ant-secret-1234', Secrets::get('anthropic_api_key'));
        $this->assertStringNotContainsString('sk-ant-secret', (string) Settings::get('secret.anthropic_api_key'), 'Stored encrypted.');
        $this->get('/system')->assertSee('••••1234')->assertDontSee('sk-ant-secret-1234');
        $this->post('/system/secrets', ['remove' => 'anthropic_api_key']);
        $this->assertNull(Secrets::get('anthropic_api_key'));
    }
}
