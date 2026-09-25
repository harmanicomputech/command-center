<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    public function test_a_coordinator_invites_an_agent_who_chooses_a_pin(): void
    {
        $coordinator = User::factory()->coordinator($this->ward('Izzi Ward 01'))->create();
        $this->actingAs($coordinator);

        $this->get('/team')->assertOk()->assertSee('No one in your team yet');
        $this->post('/team', ['name' => 'Chika Okafor', 'phone' => '0800 000 2001', 'role' => 'ward_coordinator', 'ward_id' => $this->ward('Izzi Ward 01')->id])->assertSessionHasErrors('role');
        $this->post('/team', ['name' => 'Chika Okafor', 'phone' => '0800 000 2001', 'role' => 'agent', 'ward_id' => $this->ward('Izzi Ward 02')->id])->assertSessionHasErrors('ward_id');

        $response = $this->post('/team', ['name' => 'Chika Okafor', 'phone' => '0800 000 2001', 'role' => 'agent', 'ward_id' => $this->ward('Izzi Ward 01')->id])->assertRedirect('/team');
        $invite = $response->getSession()->get('invite');
        $this->assertStringContainsString('/invite/', $invite['link']);
        $this->get('/team')->assertSee('Send Chika Okafor their link')->assertSee('wa.me/2348000002001', false);

        $agent = User::where('phone', '+2348000002001')->sole();
        $this->assertSame([UserRole::Agent, $coordinator->id], [$agent->role, $agent->invited_by]);

        auth()->logout();
        $path = parse_url($invite['link'], PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('Welcome, Chika');
        $this->post($path, ['pin' => '12', 'pin_confirmation' => '12'])->assertSessionHasErrors('pin');
        $this->post($path, ['pin' => '4455', 'pin_confirmation' => '4455'])->assertRedirect('/field');
        $this->assertAuthenticatedAs($agent);
        $this->assertTrue(Hash::check('4455', $agent->fresh()->password));

        // Links work once.
        auth()->logout();
        $this->get($path)->assertOk()->assertSee('This link has expired');
        $this->post($path, ['pin' => '1111', 'pin_confirmation' => '1111'])->assertStatus(410);
    }

    public function test_a_lost_phone_is_signed_out_everywhere(): void
    {
        $coordinator = User::factory()->coordinator($this->ward('Izzi Ward 01'))->create();
        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();

        $this->actingAs($agent)->withSession(['auth_at' => now()->subHour()->timestamp])->get('/field')->assertOk();

        $this->actingAs($coordinator)->post("/team/{$agent->id}/revoke")->assertSessionHas('status');
        $this->assertNotNull($agent->fresh()->sessions_revoked_at);

        $this->actingAs($agent->fresh())->withSession(['auth_at' => now()->subHour()->timestamp])->get('/field')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_leaders_manage_only_their_own_team(): void
    {
        $outsider = User::factory()->agent($this->ward('Abakaliki Ward 01'))->create();
        $peer = User::factory()->coordinator($this->ward('Izzi Ward 01'))->create();
        $coordinator = User::factory()->coordinator($this->ward('Izzi Ward 01'))->create();

        $this->actingAs($coordinator);
        $this->post("/team/{$outsider->id}/revoke")->assertForbidden();
        $this->post("/team/{$peer->id}/reinvite")->assertForbidden();

        $this->actingAs(User::factory()->lgaLeader($this->lga('Izzi')->id)->create());
        $this->post('/team', ['name' => 'New Coordinator', 'phone' => '0800 000 2002', 'role' => 'ward_coordinator', 'ward_id' => $this->ward('Izzi Ward 02')->id])->assertRedirect('/team');
        $this->post("/team/{$peer->id}/reinvite")->assertRedirect('/team');

        $this->actingAs(User::factory()->strategist()->create());
        $this->get('/team')->assertForbidden();
    }
}
