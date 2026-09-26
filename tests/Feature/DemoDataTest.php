<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\User;
use App\Models\Voter;
use App\Services\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    public function test_demo_data_fills_every_screen_and_removes_only_itself(): void
    {
        $this->loadSmallRegister();
        $ward = $this->ward('Izzi Ward 01');
        $realAgent = User::factory()->agent($ward)->create();
        $this->syncVoters($realAgent, [$this->voterPayload($ward->id, ['name' => 'Real Voter', 'phone' => '0800 000 6001'])])->assertOk();
        $admin = User::factory()->admin()->create();

        $demo = app(DemoData::class);
        $demo->voterCount = 400;
        $this->app->instance(DemoData::class, $demo);

        $this->actingAs(User::factory()->coordinator($ward)->create())->post('/system/demo')->assertForbidden();
        $this->actingAs($admin)->post('/system/demo')->assertRedirect()->assertSessionHas('status');
        $this->assertTrue(DemoData::loaded());
        $this->assertSame(401, Voter::query()->count());
        $this->assertGreaterThan(300, Issue::query()->count());
        $this->post('/system/demo')->assertSessionHas('error');

        $logins = DemoData::logins();
        $this->get('/system')->assertOk()->assertSee('Demo data is loaded')->assertSee($logins['password']);
        foreach (['/', '/brief', '/areas', '/segments', '/voters', '/people', '/structure', '/tasks', '/issues', '/events', '/influence', '/leaderboard', '/surveys', '/volunteers', '/messages', '/broadcasts', '/narratives', '/complaints', '/news', '/posts', '/policies'] as $path) {
            $this->get($path)->assertOk();
        }

        // The demo sign-ins work for presenting.
        auth()->logout();
        $this->post('/login', ['login' => $logins['coordinator'], 'password' => $logins['password']])->assertRedirect();
        $this->assertAuthenticated();
        auth()->logout();
        $this->post('/login', ['login' => $logins['agent'], 'password' => $logins['pin']])->assertRedirect();
        $this->get('/field')->assertOk();

        // Removing keeps real records made before or during the demo.
        $this->actingAs($admin)->delete('/system/demo')->assertRedirect();
        $this->assertFalse(DemoData::loaded());
        $this->assertSame(['Real Voter'], Voter::query()->pluck('name')->all());
        $this->assertTrue(User::query()->whereKey($realAgent->id)->exists());
        $this->assertSame(0, Issue::query()->count());
        $this->assertSame(0, DB::table('demo_records')->count());
        $this->assertSame(3, User::query()->count(), 'Only the real agent, coordinator and admin remain.');
        $this->get('/')->assertOk()->assertDontSee('Demo data is loaded');
    }
}
