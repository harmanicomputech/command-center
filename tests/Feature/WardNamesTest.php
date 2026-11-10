<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Services\PollingUnitImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

/**
 * Wards' real names: the register numbers them; admins name them, and
 * agents see both when registering a voter.
 */
class WardNamesTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
        $this->admin = User::factory()->admin()->create();
    }

    private function import(string $csv): array
    {
        $path = tempnam(sys_get_temp_dir(), 'register');
        file_put_contents($path, $csv);
        $result = app(PollingUnitImporter::class)->import($path);
        unlink($path);

        return $result;
    }

    public function test_wards_keep_a_code_and_the_registers_label(): void
    {
        $ward = $this->ward('Izzi Ward 01');

        $this->assertSame('21900003', $ward->code);
        $this->assertSame('Izzi Ward 01', $ward->register_name);
        $this->assertFalse($ward->hasOwnName());
        $this->assertSame('Izzi Ward 01', $ward->label());
    }

    public function test_an_admin_names_a_ward_and_agents_see_name_and_number(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $this->actingAs($this->admin)->get(route('areas.ward', [$ward->lga, $ward->slug]))->assertOk()->assertSee('Add its name');

        $this->actingAs($this->admin)->put(route('wards.rename', $ward), ['name' => '  Agbaja   Central '])->assertRedirect();
        $ward->refresh();
        $this->assertSame('Agbaja Central', $ward->name);
        $this->assertSame('Izzi Ward 01', $ward->register_name);
        $this->assertSame('Agbaja Central (Ward 01)', $ward->label());

        // The agent picks it by name, with the number they know.
        $agent = User::factory()->agent($ward)->create();
        $this->actingAs($agent)->get('/field/register')->assertOk()->assertSee('Agbaja Central (Ward 01)');

        // Its page and search show the name; the old label still finds it.
        $this->actingAs($this->admin)->get(route('areas.ward', [$ward->lga, $ward->slug]))->assertOk()->assertSee('Agbaja Central')->assertSee('Izzi Ward 01');
        $this->actingAs($this->admin)->getJson('/search?q=Izzi+Ward+01')->assertOk()->assertJsonPath('results.0.label', 'Agbaja Central');

        // Empty goes back to the register's label.
        $this->actingAs($this->admin)->put(route('wards.rename', $ward), ['name' => ''])->assertRedirect();
        $this->assertSame('Izzi Ward 01', $ward->fresh()->name);
    }

    public function test_two_wards_in_an_lga_cannot_share_a_name_and_only_admins_rename(): void
    {
        $this->actingAs($this->admin)->put(route('wards.rename', $this->ward('Izzi Ward 01')), ['name' => 'Izzi Ward 02'])->assertSessionHasErrors('name');

        $leader = User::factory()->lgaLeader($this->lga('Izzi')->id)->create();
        $this->actingAs($leader)->put(route('wards.rename', $this->ward('Izzi Ward 01')), ['name' => 'Anything'])->assertForbidden();
    }

    public function test_names_come_in_from_the_ward_list_csv(): void
    {
        $download = $this->actingAs($this->admin)->get(route('system.ward-names'))->assertOk()->streamedContent();
        $this->assertStringContainsString('lga,ward_code,register_name,name', $download);
        $this->assertStringContainsString('Izzi,21900003,"Izzi Ward 01",', $download);

        $csv = "lga,ward_code,register_name,name\n"
            ."Izzi,21900003,Izzi Ward 01,Agbaja\n"           // by code
            ."Abakaliki,,Abakaliki Ward 02,Azuiyiokwu\n"     // by label
            ."Izzi,21900004,Izzi Ward 02,\n"                 // left empty
            ."Izzi,99999999,Nowhere Ward,Nowhere\n";        // no such ward
        $file = UploadedFile::fake()->createWithContent('ward-names.csv', $csv);

        $this->actingAs($this->admin)->post(route('system.ward-names.import'), ['file' => $file])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Agbaja', $this->ward('Izzi Ward 01')->name);
        $this->assertSame('Azuiyiokwu', $this->ward('Abakaliki Ward 02')->name);
        $this->assertSame('Izzi Ward 02', $this->ward('Izzi Ward 02')->name);
        $this->actingAs($this->admin)->get('/system')->assertOk()->assertSee('Ward names');
    }

    public function test_a_register_with_real_ward_names_renames_wards_in_place(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create();
        $this->syncVoters($agent, [$this->voterPayload($ward->id)]);

        // INEC's file: the same polling unit codes, wards under their real names.
        $result = $this->import("code,name,ward,lga,registered_voters\nEB/219/00003/001,Primary School,Ndiagu Echara,Izzi,700\n");

        $this->assertSame([], $result['errors']);
        $ward->refresh();
        $this->assertSame('Ndiagu Echara', $ward->name);
        $this->assertSame(1, Ward::query()->where('lga_id', $ward->lga_id)->where('code', '21900003')->count(), 'No duplicate ward.');
        $this->assertSame($ward->id, Voter::query()->value('ward_id'), 'Registrations stay with their ward.');
        $this->assertSame($ward->id, $agent->fresh()->ward_id);
    }

    public function test_a_name_an_admin_gave_survives_a_register_reload(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $this->actingAs($this->admin)->put(route('wards.rename', $ward), ['name' => 'Agbaja'])->assertRedirect();

        $this->loadSmallRegister();

        $this->assertSame('Agbaja', $ward->fresh()->name);
        $this->assertSame(4, Ward::query()->count());
    }
}
