<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Influencer;
use App\Models\User;
use App\Services\Structure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

class StructureTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    public function test_engagement_is_active_occasional_or_dormant(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $active = User::factory()->agent($ward)->create(['name' => 'Active Agent']);
        $occasional = User::factory()->agent($ward)->create(['last_seen_at' => now()->subDays(2)]);
        $dormant = User::factory()->agent($ward)->create(['last_seen_at' => now()->subDays(30)]);
        $this->syncVoters($active, [$this->voterPayload($ward->id)]);

        $levels = app(Structure::class)->engagement(collect([$active, $occasional, $dormant]));
        $this->assertSame(['active', 'occasional', 'dormant'], [$levels[$active->id], $levels[$occasional->id], $levels[$dormant->id]]);

        $this->actingAs(User::factory()->lgaLeader($this->lga('Izzi')->id)->create());
        $this->get('/people')->assertOk()->assertSee('Active Agent');
        $this->get('/people?engagement=dormant')->assertDontSee('Active Agent');
        $this->get("/people/{$active->id}")->assertOk()->assertSee('Registered, all time');

        $outsider = User::factory()->agent($this->ward('Abakaliki Ward 01'))->create();
        $this->get("/people/{$outsider->id}")->assertForbidden();
    }

    public function test_wards_without_a_coordinator_or_activity_are_red(): void
    {
        $covered = $this->ward('Izzi Ward 01');
        User::factory()->coordinator($covered)->create();
        $agent = User::factory()->agent($covered)->create();
        $this->syncVoters($agent, [$this->voterPayload($covered->id)]);

        $viewer = User::factory()->lgaLeader($this->lga('Izzi')->id)->create();
        $health = app(Structure::class)->wardHealth($viewer)->keyBy(fn ($row) => $row['ward']->name);

        $this->assertFalse($health['Izzi Ward 01']['red']);
        $this->assertSame(1, $health['Izzi Ward 01']['active']);
        $this->assertSame(['No coordinator', 'No activity yet'], $health['Izzi Ward 02']['reasons']);
        $this->assertCount(2, $health);

        // Nothing for 8 days turns a covered ward red.
        DB::table('voters')->update(['captured_at' => now()->subDays(8)]);
        $this->assertSame(['Quiet for 8 days'], app(Structure::class)->wardHealth($viewer)->first()['reasons']);

        $this->actingAs($viewer)->get('/structure')->assertOk()->assertSee('Izzi Ward 02')->assertSee('No coordinator');
        $this->get('/')->assertOk()->assertSee('Wards with no activity');
    }

    public function test_influence_notes_are_kept_per_ward_within_the_area(): void
    {
        $this->actingAs(User::factory()->coordinator($this->ward('Izzi Ward 01'))->create());

        $this->post('/influence', ['ward_id' => $this->ward('Izzi Ward 01')->id, 'kind' => 'traditional_ruler', 'name' => 'The village head', 'contact_phone' => '0800 000 3001', 'relationship' => 'friendly'])->assertSessionHas('status');
        $this->post('/influence', ['ward_id' => $this->ward('Izzi Ward 02')->id, 'kind' => 'church', 'name' => 'Outside', 'relationship' => 'ally'])->assertForbidden();
        $this->post('/influence', ['ward_id' => $this->ward('Izzi Ward 01')->id, 'kind' => 'church', 'name' => 'Bad phone', 'contact_phone' => '12', 'relationship' => 'ally'])->assertSessionHasErrors('contact_phone');

        $note = Influencer::sole();
        $this->assertSame('+2348000003001', $note->contact_phone);
        $this->assertStringNotContainsString('8000003001', DB::table('influencers')->value('contact_phone'));

        $this->get('/influence')->assertOk()->assertSee('The village head')->assertSee('Friendly');
        $this->get('/areas/izzi/izzi-ward-01')->assertOk()->assertSee('The village head');
        $this->put("/influence/{$note->id}", ['ward_id' => $note->ward_id, 'kind' => 'traditional_ruler', 'name' => 'The village head', 'relationship' => 'ally'])->assertSessionHas('status');
        $this->assertSame('ally', $note->fresh()->relationship);

        $this->actingAs(User::factory()->coordinator($this->ward('Abakaliki Ward 01'))->create());
        $this->delete("/influence/{$note->id}")->assertForbidden();
    }

    public function test_events_are_planned_recorded_and_scoped(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create(['name' => 'Came Along']);
        $this->actingAs(User::factory()->coordinator($ward)->create());

        $this->post('/events', ['title' => 'Ward exco', 'type' => 'meeting', 'lga_id' => $ward->lga_id, 'ward_id' => $this->ward('Izzi Ward 02')->id, 'date' => now()->format('Y-m-d'), 'time' => '10:00'])->assertSessionHasErrors('ward_id');
        $this->post('/events', ['title' => 'Ward exco', 'type' => 'meeting', 'lga_id' => $ward->lga_id, 'ward_id' => $ward->id, 'date' => now()->addDay()->format('Y-m-d'), 'time' => '10:00', 'expected' => 40])->assertRedirect();

        $event = Event::sole();
        $this->assertSame('09:00', $event->starts_at->format('H:i'), 'Stored in UTC (10:00 in Lagos).');
        $this->get('/events')->assertOk()->assertSee('Ward exco');

        $this->post("/events/{$event->id}/record", ['status' => 'held', 'attendance' => 35, 'attendees' => [$agent->id]])->assertSessionHas('status');
        $this->assertSame([Event::HELD, 35], [$event->fresh()->status, $event->fresh()->attendance]);
        $this->assertTrue($event->attendees()->whereKey($agent->id)->exists());
        $this->get("/events/{$event->id}")->assertOk()->assertSee('Came Along');

        $this->actingAs(User::factory()->coordinator($this->ward('Abakaliki Ward 01'))->create());
        $this->get("/events/{$event->id}")->assertForbidden();
        $this->get('/events')->assertDontSee('Ward exco');
    }
}
