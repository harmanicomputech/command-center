<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    public function test_an_lga_leader_sees_only_their_lga(): void
    {
        $this->actingAs(User::factory()->lgaLeader($this->lga('Izzi')->id)->create());

        $this->get('/areas')->assertOk()->assertSee('Izzi');
        $this->get('/areas/izzi')->assertOk()->assertSee('Izzi Ward 01')->assertSee('Izzi Ward 02');
        $this->get('/areas/izzi/izzi-ward-01')->assertOk()->assertSee('EB/219/00003/001');

        // Another LGA's URL, typed in: 403.
        $this->get('/areas/abakaliki')->assertForbidden();
        $this->get('/areas/abakaliki/abakaliki-ward-01')->assertForbidden();
        $this->get('/users')->assertForbidden();
    }

    public function test_a_ward_coordinator_sees_only_their_ward(): void
    {
        $this->actingAs(User::factory()->coordinator($this->ward('Izzi Ward 01'))->create());

        $this->get('/areas/izzi')->assertOk()->assertSee('Izzi Ward 01')->assertDontSee('Izzi Ward 02');
        $this->get('/areas/izzi/izzi-ward-01')->assertOk();
        $this->get('/areas/izzi/izzi-ward-02')->assertForbidden();
        $this->get('/areas/abakaliki')->assertForbidden();
    }

    public function test_agents_use_the_field_app_not_the_command_center(): void
    {
        $this->actingAs(User::factory()->agent($this->ward('Izzi Ward 01'))->create(['name' => 'Chika Okafor']));

        $this->get('/')->assertRedirect('/field');
        $this->get('/field')->assertOk()->assertSee('Chika')->assertSee('Register a voter');
        $this->get('/field/me')->assertOk();
        $this->get('/areas')->assertForbidden();
        $this->get('/search?q=Izzi')->assertForbidden();
    }

    public function test_search_stays_inside_the_users_area(): void
    {
        User::factory()->coordinator($this->ward('Abakaliki Ward 01'))->create(['name' => 'Ngozi Abakaliki']);
        User::factory()->coordinator($this->ward('Izzi Ward 01'))->create(['name' => 'Ngozi Izzi']);
        $this->actingAs(User::factory()->lgaLeader($this->lga('Izzi')->id)->create());

        $results = collect($this->getJson('/search?q=Ngozi')->assertOk()->json('results'))->pluck('label');
        $this->assertSame(['Ngozi Izzi'], $results->all());

        $wards = collect($this->getJson('/search?q=Ward 0')->json('results'))->pluck('label');
        $this->assertEqualsCanonicalizing(['Izzi Ward 01', 'Izzi Ward 02'], $wards->all());
    }

    public function test_statewide_roles_see_everything(): void
    {
        $this->actingAs(User::factory()->strategist()->create());

        $this->get('/areas')->assertOk()->assertSee('Abakaliki')->assertSee('Izzi');
        $this->get('/areas/abakaliki/abakaliki-ward-02')->assertOk();
        $this->get('/system')->assertForbidden();
        $this->get('/design')->assertForbidden();
    }
}
