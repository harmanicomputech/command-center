<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ward;
use App\Support\Aggregates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class AggregatesTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    public function test_cached_figures_hold_plain_data_and_rehydrate_models_per_area(): void
    {
        $this->loadSmallRegister();
        config(['campaign.aggregate_cache_seconds' => 300]);
        $ward = $this->ward('Izzi Ward 01');
        $admin = User::factory()->admin()->create();
        $leader = User::factory()->lgaLeader($ward->lga_id)->create();
        $runs = 0;
        $compute = function () use (&$runs, $ward) {
            $runs++;

            return ['rows' => collect([['ward' => $ward, 'n' => 3]]), 'at' => Carbon::parse('2026-10-01 10:00:00')];
        };

        $first = Aggregates::remember('test', $admin, ['x'], $compute);
        $second = Aggregates::remember('test', $admin, ['x'], $compute);
        $this->assertSame(1, $runs);
        $this->assertInstanceOf(Ward::class, $second['rows']->first()['ward']);
        $this->assertSame($ward->id, $second['rows']->first()['ward']->id);
        $this->assertTrue($second['at']->equalTo($first['at']));

        Aggregates::remember('test', $leader, ['x'], $compute);
        $this->assertSame(2, $runs, 'Another area gets its own figures.');

        Aggregates::flush();
        Aggregates::remember('test', $admin, ['x'], $compute);
        $this->assertSame(3, $runs, 'Flushing recomputes.');
    }
}
