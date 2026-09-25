<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\User;
use App\Models\Ward;
use App\Services\PollingUnitImporter;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
    }

    public function test_the_bundled_register_has_every_ebonyi_lga_ward_and_polling_unit(): void
    {
        $result = app(PollingUnitImporter::class)->import(PollingUnitImporter::bundledPath());

        $this->assertSame(['created' => 3308, 'updated' => 0, 'errors' => []], $result);
        $this->assertSame(13, Lga::count());
        $this->assertSame(169, Ward::count());
        $this->assertEqualsCanonicalizing(config('campaign.lgas'), Lga::pluck('name')->all());

        $unit = PollingUnit::where('code', '21202633001')->sole();
        $this->assertSame(['Open Space 001', 'Abakaliki Ward 01', 'Abakaliki', 1322], [$unit->name, $unit->ward->name, $unit->ward->lga->name, $unit->registered_voters]);
        $this->assertSame('EB/212/02633/001', $unit->inecCode());

        // Totals are kept on wards and LGAs.
        $this->assertSame((int) PollingUnit::sum('registered_voters'), (int) Lga::sum('registered_voters'));
        $this->assertSame(3308, (int) Lga::sum('polling_units_count'));
        $this->assertSame(4592490, (int) Lga::sum('registered_voters'));

        // Importing again updates in place.
        $this->assertSame(['created' => 0, 'updated' => 3308, 'errors' => []], app(PollingUnitImporter::class)->import(PollingUnitImporter::bundledPath()));
        $this->assertSame(3308, PollingUnit::count());
        $this->assertSame(169, Ward::count());
    }

    public function test_admins_import_and_confirm_the_register_on_the_system_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/system/register')->assertSessionHas('status', fn ($m) => str_contains($m, '3308 polling units added'));
        $this->get('/system')->assertOk()->assertSee('Check the register before relying on it')->assertSee('4,592,490');

        $csv = "code,name,ward,lga,registered_voters\nEB/212/02633/001,New Name,Abakaliki Ward 01,Abakaliki,1500\nEB/999/1,Bad,W,Abakaliki,1\n21202633999,Extra,Abakaliki Ward 01,Nowhere,5\n";
        $this->post('/system/register', ['file' => UploadedFile::fake()->createWithContent('newer.csv', $csv)])
            ->assertSessionHas('error', fn ($m) => str_contains($m, '0 polling units added, 1 updated') && str_contains($m, 'Line 3: invalid code') && str_contains($m, 'Line 4: unknown LGA "Nowhere"'));
        $this->assertSame('New Name', PollingUnit::where('code', '21202633001')->value('name'));

        $this->post('/system/register', ['file' => UploadedFile::fake()->createWithContent('wrong.csv', "pu,name\n1,x\n")])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no "code" column'));

        $this->post('/system/register/confirm')->assertSessionHasErrors('confirm');
        $this->post('/system/register/confirm', ['confirm' => '1'])->assertSessionHas('status');
        $this->get('/system')->assertDontSee('Check the register before relying on it')->assertSee('Confirmed by');
        $this->assertSame(2, AuditLog::where('action', 'system.register_import')->count());
        $this->assertTrue(AuditLog::where('action', 'system.register_confirm')->exists());
    }

    public function test_an_official_upload_confirms_the_register(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $csv = "code,name,ward,lga,registered_voters\nEB/212/02633/001,School,Abakaliki Ward 01,Abakaliki,900\n";

        $this->post('/system/register', ['file' => UploadedFile::fake()->createWithContent('inec.csv', $csv), 'official' => '1'])->assertSessionHas('status');
        $this->assertNotNull(Settings::get('register.confirmed_at'));
    }

    public function test_only_admins_touch_the_register(): void
    {
        $this->actingAs(User::factory()->strategist()->create());

        $this->post('/system/register')->assertForbidden();
        $this->post('/system/register/confirm', ['confirm' => '1'])->assertForbidden();
    }

    public function test_the_command_and_the_seeder(): void
    {
        $this->artisan('pu:import')->expectsOutput('3308 polling units added, 0 updated.')->assertSuccessful();
        $this->seed();
        $this->assertSame(3308, PollingUnit::count());
    }
}
