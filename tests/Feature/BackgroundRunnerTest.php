<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BackgroundRunner;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackgroundRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        config(['campaign.background_runner' => true]);
    }

    public function test_the_pinger_url_runs_the_work(): void
    {
        $this->get('/cron/wrong-token')->assertNotFound();
        $this->getJson('/cron/'.BackgroundRunner::token())->assertOk()->assertJson(['status' => 'ran']);

        Settings::flush();
        $this->assertNotNull(Settings::get('runner.heartbeat'));
        $this->assertSame('pinger', Settings::get('runner.source'));
    }

    public function test_the_tick_command_runs_the_work(): void
    {
        $this->artisan('app:tick')->expectsOutput('Background work done.')->assertSuccessful();
        Settings::flush();
        $this->assertSame('cron', Settings::get('runner.source'));
    }

    public function test_the_system_page_shows_the_pinger_url_and_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/system')->assertOk()->assertSee(route('runner', BackgroundRunner::token()))->assertSee('Not running');
        $this->get('/cron/'.BackgroundRunner::token());
        Settings::flush();
        $this->get('/system')->assertSee('✓ Running')->assertSee('(by the pinger)');
    }

    public function test_slots_run_each_task_once_per_period(): void
    {
        $at = Carbon::parse('2026-10-05 06:14:00', 'UTC'); // 07:14 in Lagos, a Monday

        $this->assertSame('2026-10-05 06:00', BackgroundRunner::everyMinutes($at, 15));
        $this->assertSame('2026-10-05', BackgroundRunner::dailyAt($at, '07:00'));
        $this->assertNull(BackgroundRunner::dailyAt($at, '08:00'));
        $this->assertSame('2026-10-05', BackgroundRunner::weekly($at));
        $this->assertSame('2026-10-05', BackgroundRunner::weekly($at->copy()->addDays(6)));
    }

    public function test_admins_can_update_the_database(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/system/migrate')->assertRedirect()->assertSessionHas('status', 'The database is up to date.');
    }
}
