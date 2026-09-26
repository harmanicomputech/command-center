<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushAlerts;
use App\Services\PushNotifier;
use App\Support\BackgroundRunner;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class PushAndBriefTest extends TestCase
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

    public function test_admins_create_the_keys_once_and_people_subscribe_their_devices(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post('/system/push-keys')->assertSessionHas('status');
        $this->post('/system/push-keys')->assertSessionHas('error');
        $this->assertTrue(app(PushNotifier::class)->configured());

        $this->get('/notifications')->assertOk()->assertSee('The daily brief is ready (7 AM)');
        $this->postJson('/push/subscribe', ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'auth'], 'topics' => ['daily_brief', 'security']])->assertOk();
        $this->assertSame(['daily_brief', 'security'], PushSubscription::sole()->topics);

        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        $this->actingAs($agent)->get('/notifications')->assertOk()->assertSee('A new task for me')->assertDontSee('The daily brief is ready');
        $this->postJson('/push/subscribe', ['endpoint' => 'https://push.example.com/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a'], 'topics' => ['daily_brief']])->assertUnprocessable();
    }

    public function test_new_tasks_and_fresh_security_reports_queue_alerts_for_the_right_people(): void
    {
        $sent = new class extends PushNotifier
        {
            public array $calls = [];

            public function toTopic(string $topic, array $message, ?int $lgaId = null, ?array $userIds = null): int
            {
                $this->calls[] = ['topic' => $topic, 'message' => $message, 'lga' => $lgaId, 'users' => $userIds];

                return 1;
            }
        };
        $this->app->instance(PushNotifier::class, $sent);
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create();
        $this->actingAs(User::factory()->coordinator($ward)->create());

        $this->post('/tasks', ['title' => 'Flyers', 'type' => 'flyers', 'ward_id' => $ward->id, 'proof' => 'none']);
        $this->assertSame(['task_assigned', [$agent->id]], [$sent->calls[0]['topic'], $sent->calls[0]['users']]);
        $sent->calls = [];

        $report = fn (string $category, string $at) => $this->actingAs($agent)->postJson('/api/field/sync', ['items' => [['id' => (string) Str::uuid(), 'type' => 'issue', 'payload' => [
            'category' => $category, 'description' => 'Armed robbers on the farm road at night.', 'severity' => 'high', 'ward_id' => $ward->id, 'reported_at' => $at,
        ]]]]);

        $report('security', now()->subDays(2)->toIso8601String());
        $this->assertSame([], $sent->calls, 'An old report synced late raises no alarm.');
        $report('water', now()->toIso8601String());
        $this->assertSame([], $sent->calls);
        $report('security', now()->toIso8601String());
        $this->assertSame(['security', $ward->lga_id, true], [$sent->calls[0]['topic'], $sent->calls[0]['lga'], $sent->calls[0]['message']['urgent']]);
        $this->assertCount(1, $sent->calls, 'Each alert is sent once.');
    }

    public function test_the_daily_brief_runs_once_after_7am_lagos_time(): void
    {
        config(['campaign.background_runner' => true]);
        $this->travelTo(now()->setTimezone('Africa/Lagos')->setTime(6, 30)->utc());
        app(BackgroundRunner::class)->run(queueSeconds: 1);
        Settings::flush();
        $this->assertNull(Settings::get('runner.slot.daily-brief'));

        $this->travelTo(now()->setTimezone('Africa/Lagos')->setTime(7, 5)->utc());
        app(BackgroundRunner::class)->run(queueSeconds: 1);
        Settings::flush();
        $this->assertSame(now()->setTimezone('Africa/Lagos')->format('Y-m-d'), Settings::get('runner.slot.daily-brief'));
    }

    public function test_the_dashboard_and_the_printable_brief(): void
    {
        $this->actingAs(User::factory()->strategist()->create());

        $this->get('/')->assertOk()->assertSee('Where we’re winning', false)->assertSee('What to push next')->assertSee('Priority wards');
        $this->get('/brief')->assertOk()->assertSee('daily brief')->assertSee('Print or save as PDF');

        Settings::set('brief.suggestion', 'Lead with water in Izzi this week.');
        $this->get('/')->assertSee('AI suggestion, not a decision')->assertSee('Lead with water in Izzi');
    }
}
