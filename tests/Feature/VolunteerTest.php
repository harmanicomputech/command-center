<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\JoinController;
use App\Models\User;
use App\Models\Volunteer;
use App\Services\Erasure;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class VolunteerTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    public function test_the_join_page_and_the_website_endpoint_record_volunteers_with_consent(): void
    {
        $this->get('/join')->assertOk()->assertSee('Join the movement');
        $this->post('/join', ['name' => 'Test Volunteer', 'phone' => '0800 000 7001', 'lga' => $this->lga('Izzi')->id])->assertSessionHasErrors('consent');
        $this->post('/join', ['name' => 'Bot', 'phone' => '0800 000 7009', 'lga' => $this->lga('Izzi')->id, 'consent' => '1', 'website' => 'x'])->assertSessionHas('joined');
        $this->assertSame(0, Volunteer::query()->count(), 'The honeypot catches bots.');

        $this->post('/join', ['name' => 'Test Volunteer', 'phone' => '0800 000 7001', 'lga' => $this->lga('Izzi')->id, 'ward' => 'Izzi Ward 01', 'help' => ['canvassing', 'events'], 'consent' => '1'])->assertRedirect('/join');
        $volunteer = Volunteer::sole();
        $this->assertSame(['Izzi Ward 01', ['canvassing', 'events'], 'join page', '1'], [$volunteer->ward->name, $volunteer->help, $volunteer->source, $volunteer->consent_version]);
        $this->assertStringNotContainsString('0800', (string) \DB::table('volunteers')->value('phone'), 'Encrypted at rest.');

        // The website forwards by LGA name; the same number updates its row.
        $token = JoinController::token();
        $this->postJson('/api/volunteers/wrong', [])->assertNotFound();
        $this->postJson("/api/volunteers/{$token}", ['name' => 'X', 'phone' => '12', 'lga' => 'Izzi', 'consent' => 1])->assertStatus(422)->assertJsonPath('status', 'invalid');
        $this->postJson("/api/volunteers/{$token}", ['name' => 'Web Volunteer', 'phone' => '+234 800 000 7002', 'lga' => 'Abakaliki', 'help' => ['social_media'], 'consent' => 1])->assertOk()->assertJson(['status' => 'ok']);
        $this->postJson("/api/volunteers/{$token}", ['name' => 'Test Volunteer', 'phone' => '08000007001', 'lga' => 'Izzi', 'consent' => 1])->assertOk();
        $this->assertSame(2, Volunteer::query()->count());
        $this->assertSame('website', Volunteer::query()->where('name', 'Web Volunteer')->value('source'));
    }

    public function test_coordinators_see_their_area_and_make_volunteers_agents(): void
    {
        $izzi = $this->ward('Izzi Ward 01');
        Volunteer::create(['name' => 'In Ward', 'phone' => '+2348000007011', 'phone_hash' => Phone::hash('08000007011'), 'lga_id' => $izzi->lga_id, 'ward_id' => $izzi->id, 'source' => 'join page', 'consent_at' => now(), 'consent_version' => '1']);
        Volunteer::create(['name' => 'Elsewhere', 'phone' => '+2348000007012', 'phone_hash' => Phone::hash('08000007012'), 'lga_id' => $this->lga('Abakaliki')->id, 'source' => 'join page', 'consent_at' => now(), 'consent_version' => '1']);

        $coordinator = User::factory()->coordinator($izzi)->create();
        $this->actingAs($coordinator)->get('/volunteers')->assertOk()->assertSee('In Ward')->assertDontSee('Elsewhere');
        $elsewhere = Volunteer::query()->where('name', 'Elsewhere')->sole();
        $this->post("/volunteers/{$elsewhere->id}/status", ['status' => 'contacted'])->assertForbidden();

        $volunteer = Volunteer::query()->where('name', 'In Ward')->sole();
        $this->post("/volunteers/{$volunteer->id}/invite", ['ward_id' => $izzi->id])->assertRedirect('/team')->assertSessionHas('invite');
        $agent = User::query()->where('phone', '+2348000007011')->sole();
        $this->assertSame([UserRole::Agent, $izzi->id], [$agent->role, $agent->ward_id]);
        $this->assertSame(['joined', $agent->id], [$volunteer->refresh()->status, $volunteer->user_id]);

        // Delete-my-data removes a volunteer who hasn't joined.
        app(Erasure::class)->erasePhone('08000007012', 'test');
        $this->assertFalse(Volunteer::query()->where('name', 'Elsewhere')->exists());
    }
}
