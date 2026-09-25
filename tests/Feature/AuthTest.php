<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PollingUnit;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
    }

    public function test_the_first_admin_is_created_with_the_setup_key_and_the_register_loads(): void
    {
        $this->get('/login')->assertOk()->assertSee('Create the first admin account');

        $this->post('/setup', ['setup_key' => 'wrong', 'name' => 'Ada Admin', 'email' => 'a@example.com', 'password' => 'long-password', 'password_confirmation' => 'long-password'])
            ->assertSessionHasErrors('setup_key');

        $this->post('/setup', ['setup_key' => 'setup-key', 'name' => 'Ada Admin', 'email' => 'A@Example.com', 'password' => 'long-password', 'password_confirmation' => 'long-password'])
            ->assertRedirect('/system')
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'The register of 3308 polling units is loaded.'));

        $user = User::firstOrFail();
        $this->assertSame('a@example.com', $user->email);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertAuthenticatedAs($user);
        $this->assertSame(3308, PollingUnit::count());

        // Once an account exists, set-up is closed.
        auth()->logout();
        $this->post('/setup', ['setup_key' => 'setup-key', 'name' => 'Other', 'email' => 'o@example.com', 'password' => 'long-password', 'password_confirmation' => 'long-password'])
            ->assertRedirect('/login');
        $this->assertSame(1, User::count());
    }

    public function test_staff_sign_in_with_email_and_agents_with_phone_and_pin(): void
    {
        $this->loadSmallRegister();
        $staff = User::factory()->create(['email' => 'lead@example.com']);
        $agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create(['phone' => '+2348000000001']);

        $this->post('/login', ['login' => 'lead@example.com', 'password' => 'nope'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'Lead@example.com', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($staff);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        // 0800 000 0001 and +234 800 000 0001 are the same number.
        $this->post('/login', ['login' => '0800 000 0001', 'password' => '1234'])->assertRedirect('/field');
        $this->assertAuthenticatedAs($agent);
        $this->assertTrue(AuditLog::where('action', 'auth.failed')->exists());
    }

    public function test_switched_off_accounts_cannot_sign_in_and_are_signed_out(): void
    {
        $user = User::factory()->create(['email' => 'off@example.com']);
        $this->actingAs($user)->get('/')->assertOk();

        $user->forceFill(['disabled_at' => now()])->save();
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();

        $this->post('/login', ['login' => 'off@example.com', 'password' => 'password'])->assertSessionHasErrors('login');
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        User::factory()->create();

        foreach (['/', '/areas', '/users', '/system', '/settings', '/field', '/design'] as $page) {
            $this->get($page)->assertRedirect('/login');
        }
    }
}
