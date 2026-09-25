<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voter;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

class FieldSyncTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
        $this->agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
    }

    public function test_the_outbox_needs_a_session_and_gets_a_fresh_csrf_token(): void
    {
        $this->getJson('/api/field/token')->assertUnauthorized();
        $this->postJson('/api/field/sync', ['items' => []])->assertUnauthorized();

        $this->actingAs($this->agent)->getJson('/api/field/token')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_registration_syncs_once_however_often_it_is_sent(): void
    {
        $uuid = (string) Str::uuid();
        $payload = $this->voterPayload($this->ward('Izzi Ward 01')->id);

        $this->syncVoters($this->agent, [$uuid => $payload])->assertOk()
            ->assertJsonPath('results.0.id', $uuid)
            ->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.0.data.points', 3);
        $this->syncVoters($this->agent, [$uuid => $payload])->assertJsonPath('results.0.status', 'ok');

        $voter = Voter::sole();
        $this->assertSame(['Test Voter', '+2348000001001', 'unverified', $this->agent->id, '1'], [$voter->name, $voter->phone, $voter->status, $voter->captured_by, $voter->consent_version]);
        $this->assertSame($this->ward('Izzi Ward 01')->lga_id, $voter->lga_id);
        $this->assertNotNull($voter->consent_at);

        // Encrypted at rest, with a keyed hash for de-duplication.
        $raw = DB::table('voters')->value('phone');
        $this->assertStringNotContainsString('8000001001', $raw);
        $this->assertSame(Phone::hash('08000001001'), $voter->phone_hash);
    }

    public function test_no_consent_no_record_and_bad_items_come_back_invalid(): void
    {
        $ward = $this->ward('Izzi Ward 01')->id;
        $response = $this->syncVoters($this->agent, [
            $this->voterPayload($ward, ['consent' => false]),
            $this->voterPayload($ward, ['phone' => '12345']),
            $this->voterPayload($this->ward('Abakaliki Ward 01')->id),
            $this->voterPayload($ward, ['support_level' => 'maybe']),
        ])->assertOk();

        $this->assertSame(['invalid', 'invalid', 'invalid', 'invalid'], array_column($response->json('results'), 'status'));
        $this->assertSame('The voter must agree before we can save their details.', $response->json('results.0.message'));
        $this->assertSame('Choose a ward in your LGA.', $response->json('results.2.message'));
        $this->assertSame(0, Voter::count());

        $this->actingAs($this->agent)->postJson('/api/field/sync', ['items' => [
            ['id' => 'not-a-uuid', 'type' => 'voter', 'payload' => []],
            ['id' => (string) Str::uuid(), 'type' => 'spaceship', 'payload' => []],
        ]])->assertJsonPath('results.0.status', 'invalid')->assertJsonPath('results.1.status', 'invalid');
    }

    public function test_agents_can_register_in_another_ward_of_their_lga(): void
    {
        $this->syncVoters($this->agent, [$this->voterPayload($this->ward('Izzi Ward 02')->id)])->assertJsonPath('results.0.status', 'ok');
        $this->assertSame($this->ward('Izzi Ward 02')->id, Voter::sole()->ward_id);
    }

    public function test_the_same_phone_twice_is_flagged_as_a_possible_duplicate(): void
    {
        $ward = $this->ward('Izzi Ward 01')->id;
        $other = User::factory()->agent($this->ward('Izzi Ward 02'))->create();

        $this->syncVoters($this->agent, [$this->voterPayload($ward)]);
        $this->syncVoters($other, [$this->voterPayload($ward, ['name' => 'Same Phone', 'phone' => '+234 800 000 1001'])])
            ->assertJsonPath('results.0.data.duplicate', true)
            ->assertJsonPath('results.0.data.points', 0);

        $duplicate = Voter::where('name', 'Same Phone')->sole();
        $this->assertTrue($duplicate->possible_duplicate);
        $this->assertSame(Voter::where('name', 'Test Voter')->value('id'), $duplicate->duplicate_of);
        $this->assertSame(1, Voter::counted()->count());
    }

    public function test_a_wrong_phone_clock_does_not_date_records_in_the_future(): void
    {
        $this->syncVoters($this->agent, [$this->voterPayload($this->ward('Izzi Ward 01')->id, ['captured_at' => now()->addYear()->toIso8601String()])]);

        $this->assertTrue(Voter::sole()->captured_at->lte(now()->addMinute()));
    }

    public function test_the_form_still_works_without_scripts(): void
    {
        $uuid = (string) Str::uuid();
        $this->actingAs($this->agent)->get('/field/register')->assertOk()->assertSee('Izzi Ward 01')->assertSee('Privacy notice');

        $payload = ['uuid' => $uuid, ...$this->voterPayload($this->ward('Izzi Ward 01')->id), 'consent' => '1'];
        $this->post('/field/register', $payload)->assertRedirect('/field/register');
        $this->post('/field/register', $payload)->assertRedirect('/field/register');
        $this->assertSame(1, Voter::count());

        $this->post('/field/register', [...$payload, 'uuid' => (string) Str::uuid(), 'consent' => '0'])->assertSessionHasErrors('consent');
    }

    public function test_the_agent_sees_their_own_numbers_masked_and_their_stats(): void
    {
        $this->syncVoters($this->agent, [$this->voterPayload($this->ward('Izzi Ward 01')->id)]);

        $this->get('/field/registrations')->assertOk()->assertSee('Test Voter')->assertSee('0800 *** **01')->assertDontSee('0800 000 1001');
        $this->get('/field')->assertOk()->assertSee('1-day streak')->assertSee('#1 of 1 in your ward');
        $this->get('/privacy')->assertOk()->assertSee('never record your religion');
    }
}
