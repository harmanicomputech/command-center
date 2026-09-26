<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Issue;
use App\Models\Reward;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use App\Models\Voter;
use App\Services\Badges;
use App\Services\Points;
use App\Services\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    public function test_points_come_from_the_records_and_follow_verification(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create();
        $this->syncVoters($agent, [
            $this->voterPayload($ward->id, ['phone' => '0800 000 4001']),
            $this->voterPayload($ward->id, ['phone' => '0800 000 4002']),
            $this->voterPayload($ward->id, ['phone' => '0800 000 4003']),
        ]);
        [$first, $second] = Voter::orderBy('id')->get();
        $first->update(['status' => 'verified']);
        $second->update(['status' => 'invalid']);

        $task = Task::create(['title' => 'T', 'type' => 'flyers', 'lga_id' => $ward->lga_id, 'ward_id' => $ward->id, 'proof' => 'none', 'status' => 'open']);
        TaskReport::create(['uuid' => (string) Str::uuid(), 'task_id' => $task->id, 'user_id' => $agent->id, 'done' => true, 'reported_at' => now()]);
        Issue::create(['uuid' => (string) Str::uuid(), 'category' => 'water', 'description' => 'No borehole', 'severity' => 'low', 'lga_id' => $ward->lga_id, 'ward_id' => $ward->id, 'reported_by' => $agent->id, 'reported_at' => now(), 'status' => 'noted']);
        Issue::create(['uuid' => (string) Str::uuid(), 'category' => 'water', 'description' => 'Rejected', 'severity' => 'low', 'lga_id' => $ward->lga_id, 'ward_id' => $ward->id, 'reported_by' => $agent->id, 'reported_at' => now(), 'status' => 'rejected']);
        $event = Event::create(['title' => 'E', 'type' => 'meeting', 'lga_id' => $ward->lga_id, 'ward_id' => $ward->id, 'starts_at' => now()->subHour(), 'status' => 'held']);
        $event->attendees()->attach($agent->id, ['created_at' => now()]);

        // 10 (verified) + 0 (invalid) + 3 (unverified) + 15 + 5 + 5.
        $this->assertSame(38, app(Points::class)->totals([$agent->id])[$agent->id]['points']);

        // Last week's work isn't on this week's board.
        DB::table('voters')->update(['captured_at' => now()->subWeeks(2)]);
        $this->assertSame(25, app(Points::class)->totals([$agent->id], Points::weekStart())[$agent->id]['points']);
    }

    public function test_leaderboards_rank_agents_wards_and_lgas_and_the_agent_sees_their_own_row(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $leader = User::factory()->agent($ward)->create(['name' => 'Top Agent']);
        $second = User::factory()->agent($ward)->create(['name' => 'Second Agent']);
        $this->syncVoters($leader, [$this->voterPayload($ward->id, ['phone' => '0800 000 5001']), $this->voterPayload($ward->id, ['phone' => '0800 000 5002'])]);
        $this->syncVoters($second, [$this->voterPayload($ward->id, ['phone' => '0800 000 5003'])]);

        $board = app(Points::class)->agents($ward->id);
        $this->assertSame(['Top Agent', 'Second Agent'], $board->take(2)->pluck('user.name')->all());
        $this->assertSame([1, 2], $board->take(2)->pluck('rank')->all());

        $this->actingAs($second)->get('/field/leaderboard')->assertOk()->assertSee('Top Agent')->assertSee('#2');
        $this->get('/field')->assertSee('#2 of 2 in your ward');

        $this->actingAs(User::factory()->strategist()->create());
        $this->get('/leaderboard')->assertOk()->assertSee('Top Agent');
        $this->get('/leaderboard?level=wards')->assertOk()->assertSee('Izzi Ward 01');
        $this->get('/leaderboard?level=lgas&period=all')->assertOk()->assertSee('Izzi');
    }

    public function test_badges_and_rewards(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create();
        $this->syncVoters($agent, collect(range(1, 10))->map(fn ($i) => $this->voterPayload($ward->id, ['phone' => '0800 000 60'.str_pad($i, 2, '0', STR_PAD_LEFT)]))->all());

        $badges = app(Badges::class)->for($agent);
        $this->assertTrue($badges['first_10']);
        $this->assertFalse($badges['club_100']);

        $this->actingAs(User::factory()->strategist()->create());
        $this->post('/leaderboard/rewards', ['user_id' => $agent->id, 'kind' => 'airtime'])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        $this->post('/leaderboard/rewards', ['user_id' => $agent->id, 'kind' => 'airtime', 'description' => 'Sample airtime'])->assertSessionHas('status');
        $this->assertSame(1, Reward::count());
        $this->assertTrue(AuditLog::where('action', 'rewards.create')->exists());
        $this->get('/leaderboard')->assertSee('Sample airtime');
    }

    public function test_suspicious_patterns_are_flagged_privately(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create(['name' => 'Fast Agent']);
        $this->syncVoters($agent, collect(range(1, 9))->map(fn ($i) => $this->voterPayload($ward->id, ['phone' => '0800 000 700'.$i, 'support_level' => 'strong']))->all());

        $kinds = app(Review::class)->flags(User::factory()->coordinator($ward)->create())->pluck('kind')->all();
        $this->assertContains('burst', $kinds);
        $this->assertContains('phones', $kinds);

        $this->actingAs(User::factory()->coordinator($ward)->create())->get('/leaderboard/review')->assertOk()->assertSee('Fast Agent');
        $this->actingAs(User::factory()->strategist()->create())->get('/leaderboard/review')->assertForbidden();
        $this->actingAs($agent)->get('/leaderboard/review')->assertForbidden();
    }
}
