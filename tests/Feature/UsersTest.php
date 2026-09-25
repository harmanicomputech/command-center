<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    public function test_admins_add_staff_and_agents_with_the_right_sign_in(): void
    {
        $this->loadSmallRegister();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $ward = $this->ward('Izzi Ward 01');

        // Agents need a phone and a PIN, and a ward.
        $this->post('/users', ['name' => 'Chika', 'role' => 'agent', 'phone' => '12345', 'password' => 'abcd'])
            ->assertSessionHasErrors(['phone', 'password', 'ward_id']);
        $this->post('/users', ['name' => 'Chika Okafor', 'role' => 'agent', 'phone' => '0800 000 0002', 'password' => '4321', 'ward_id' => $ward->id])
            ->assertRedirect('/users');

        $agent = User::where('phone', '+2348000000002')->sole();
        $this->assertSame(UserRole::Agent, $agent->role);
        $this->assertSame([$ward->id, $ward->lga_id], [$agent->ward_id, $agent->lga_id]);
        $this->assertTrue(Hash::check('4321', $agent->password));
        $this->assertSame($admin->id, $agent->invited_by);

        // The same phone twice is refused.
        $this->post('/users', ['name' => 'Twin', 'role' => 'agent', 'phone' => '+234 800 000 0002', 'password' => '1111', 'ward_id' => $ward->id])->assertSessionHasErrors('phone');

        // Staff need an email and a long password; LGA leaders an LGA.
        $this->post('/users', ['name' => 'Lead', 'role' => 'lga_leader', 'password' => 'short'])->assertSessionHasErrors(['email', 'password', 'lga_id']);
        $this->post('/users', ['name' => 'Lead', 'role' => 'lga_leader', 'email' => 'LEAD@example.com', 'password' => 'long-password', 'lga_id' => $this->lga('Izzi')->id])->assertRedirect();
        $this->assertSame(UserRole::LgaLeader, User::where('email', 'lead@example.com')->sole()->role);

        $this->get('/users')->assertOk()->assertSee('Chika Okafor')->assertSee('0800 000 0002');
        $this->get('/users?role=agent')->assertOk()->assertSee('Chika Okafor')->assertDontSee('lead@example.com');
    }

    public function test_admins_edit_switch_off_and_delete_accounts_but_not_their_own(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Strat Egist']);
        $this->actingAs($admin);

        $this->get("/users/{$user->id}/edit")->assertOk()->assertSee('Strat Egist');
        $this->put("/users/{$user->id}", ['name' => 'Strat Egist', 'role' => 'admin', 'email' => $user->email])->assertRedirect('/users');
        $this->assertTrue($user->fresh()->isAdmin());

        $this->put("/users/{$admin->id}", ['name' => $admin->name, 'role' => 'strategist', 'email' => $admin->email])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->isAdmin());

        $this->post("/users/{$user->id}/toggle")->assertSessionHas('status');
        $this->assertTrue($user->fresh()->isDisabled());
        $this->post("/users/{$user->id}/toggle");
        $this->assertFalse($user->fresh()->isDisabled());
        $this->post("/users/{$admin->id}/toggle")->assertSessionHas('error');

        $this->delete("/users/{$admin->id}")->assertSessionHas('error');
        $this->delete("/users/{$user->id}")->assertRedirect('/users');
        $this->assertNull($user->fresh());
    }

    public function test_people_change_their_own_password_or_pin(): void
    {
        $this->loadSmallRegister();
        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        $this->actingAs($agent);

        $this->put('/account/password', ['current_password' => '1234', 'password' => 'abcdef', 'password_confirmation' => 'abcdef'])->assertSessionHasErrors('password');
        $this->put('/account/password', ['current_password' => '1234', 'password' => '98765', 'password_confirmation' => '98765'])->assertSessionHas('status');
        $this->assertTrue(Hash::check('98765', $agent->fresh()->password));
    }
}
