<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    private Voter $voter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        $this->syncVoters($agent, [$this->voterPayload($this->ward('Izzi Ward 01')->id)]);
        $this->voter = Voter::sole();
    }

    public function test_coordinators_call_and_verify_registrations_in_their_ward(): void
    {
        $this->actingAs(User::factory()->coordinator($this->ward('Izzi Ward 01'))->create());

        $this->get('/voters')->assertOk()->assertSee('Test Voter')->assertSee('0800 000 1001')->assertSee('tel:+2348000001001', false);
        $this->get('/voters?filter=spot-check')->assertOk()->assertSee('Test Voter');
        $this->get('/voters?q=08000001001')->assertSee('Test Voter');
        $this->get('/voters?q=Nobody')->assertDontSee('Test Voter');

        $this->post("/voters/{$this->voter->id}/verify")->assertSessionHas('status');
        $this->assertSame('verified', $this->voter->fresh()->status);

        $this->post("/voters/{$this->voter->id}/invalid", ['note' => 'Number not reachable'])->assertSessionHas('status');
        $this->assertSame(['invalid', 'Number not reachable'], [$this->voter->fresh()->status, $this->voter->fresh()->verification_note]);
    }

    public function test_nobody_verifies_outside_their_area_and_strategists_only_look(): void
    {
        $this->actingAs(User::factory()->coordinator($this->ward('Izzi Ward 02'))->create());
        $this->get('/voters')->assertOk()->assertDontSee('Test Voter');
        $this->post("/voters/{$this->voter->id}/verify")->assertForbidden();

        $this->actingAs(User::factory()->strategist()->create());
        $this->get('/voters')->assertOk()->assertSee('Test Voter')->assertDontSee('Spot-check 10');
        $this->post("/voters/{$this->voter->id}/verify")->assertForbidden();

        $this->actingAs(User::factory()->agent($this->ward('Izzi Ward 01'))->create());
        $this->get('/voters')->assertForbidden();
    }

    public function test_possible_duplicates_are_resolved_by_a_coordinator(): void
    {
        $other = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        $this->syncVoters($other, [$this->voterPayload($this->ward('Izzi Ward 01')->id, ['name' => 'Twin'])]);
        $twin = Voter::where('name', 'Twin')->sole();

        $this->actingAs(User::factory()->coordinator($this->ward('Izzi Ward 01'))->create());
        $this->get('/voters?filter=duplicates')->assertSee('Twin')->assertSee('Same phone as');

        $this->post("/voters/{$twin->id}/resolve", ['decision' => 'different']);
        $this->assertFalse($twin->fresh()->possible_duplicate);
        $this->assertSame(2, Voter::counted()->count());

        $twin->update(['possible_duplicate' => true]);
        $this->post("/voters/{$twin->id}/resolve", ['decision' => 'same']);
        $this->assertSame('invalid', $twin->fresh()->status);
        $this->assertSame(1, Voter::counted()->count());
    }

    public function test_only_admins_export_and_every_export_is_logged_with_its_row_count(): void
    {
        $this->actingAs(User::factory()->strategist()->create());
        $this->get('/voters/export')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        $csv = $this->get('/voters/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Test Voter', $csv);
        $this->assertSame(1, AuditLog::where('action', 'voters.export')->value('rows'));
    }
}
