<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Segment;
use App\Models\User;
use App\Services\Intelligence;
use App\Services\WardMap;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

class IntelligenceTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    /** Canvass $n voters in a ward with the given support level. */
    private function canvass(string $ward, int $n, string $support, int $offset = 0): void
    {
        $wardModel = $this->ward($ward);
        $agent = User::factory()->agent($wardModel)->create();
        $this->syncVoters($agent, collect(range(1, $n))->map(fn ($i) => $this->voterPayload($wardModel->id, ['phone' => null, 'support_level' => $support]))->all());
    }

    private function row(string $ward): array
    {
        return app(Intelligence::class)->wards()->firstWhere('name', $ward);
    }

    public function test_with_no_data_every_ward_is_unknown(): void
    {
        $row = $this->row('Izzi Ward 01');
        $this->assertSame(['unknown', null, 'No data yet'], [$row['zone'], $row['share'], $row['basis']]);
        $this->assertSame(700, $row['priority'], 'Unknown wards keep full priority: registered × 1 × reach.');
    }

    public function test_canvassing_alone_classifies_a_ward_once_the_sample_is_big_enough(): void
    {
        Settings::set('intel.min_sample', '10');
        $this->canvass('Izzi Ward 01', 9, 'strong');
        $this->assertSame('unknown', $this->row('Izzi Ward 01')['zone']);
        $this->assertStringContainsString('only 9 canvassed', $this->row('Izzi Ward 01')['basis']);

        $this->canvass('Izzi Ward 01', 1, 'strong');
        $row = $this->row('Izzi Ward 01');
        $this->assertSame(['stronghold', 100.0, ['canvass']], [$row['zone'], $row['share'], $row['sources']]);
        $this->assertSame('Based on 10 canvassed voters', $row['basis']);

        $this->canvass('Izzi Ward 02', 10, 'opponent');
        $this->assertSame('weak', $this->row('Izzi Ward 02')['zone']);
    }

    public function test_past_results_blend_with_canvassing_and_fall_back_to_the_lga_figure(): void
    {
        Settings::set('campaign.party', 'apc');
        Settings::set('intel.min_sample', '10');
        $this->actingAs(User::factory()->admin()->create());

        $csv = "lga,ward,party,votes\nIzzi,Izzi Ward 01,APC,300\nIzzi,Izzi Ward 01,PDP,700\nIzzi,,APC,600\nIzzi,,PDP,400\nIzzi,Nowhere Ward,APC,5\n";
        $this->post('/results', ['year' => 2023, 'file' => UploadedFile::fake()->createWithContent('2023.csv', $csv)])
            ->assertSessionHas('error', fn ($m) => str_contains($m, '4 rows imported') && str_contains($m, 'unknown ward "Nowhere Ward"'));
        $this->assertTrue(AuditLog::where('action', 'results.import')->exists());

        // Ward 01 has its own figure (30%): weak. Ward 02 uses the LGA's (60%).
        $this->assertSame(['weak', 30.0], [$this->row('Izzi Ward 01')['zone'], $this->row('Izzi Ward 01')['share']]);
        $this->assertSame(['stronghold', 60.0], [$this->row('Izzi Ward 02')['zone'], $this->row('Izzi Ward 02')['share']]);
        $this->assertStringContainsString('the 2023 result (LGA figure)', $this->row('Izzi Ward 02')['basis']);

        // Results 50% weight, canvassing 35%: (30×50 + 100×35) / 85 = 58.8.
        $this->canvass('Izzi Ward 01', 10, 'strong');
        $row = $this->row('Izzi Ward 01');
        $this->assertSame(58.8, $row['share']);
        $this->assertSame('stronghold', $row['zone']);

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/results')->assertOk()->assertSee('Used for zones')->assertSee('60%');
        $this->get('/areas')->assertOk()->assertSee('Stronghold');
        $this->delete('/results/2023')->assertSessionHas('status');
        $this->assertSame('stronghold', $this->row('Izzi Ward 01')['zone'], 'Canvassing alone now: 100%.');
    }

    public function test_priority_follows_size_certainty_and_reach_presets(): void
    {
        $this->assertSame('Priority mobilisation', Intelligence::tag('izzi'));
        $this->assertSame(700, $this->row('Izzi Ward 01')['priority']);

        $this->actingAs(User::factory()->strategist()->create());
        $this->put('/presets', ['reach' => ['izzi' => 50], 'tag' => ['izzi' => 'Hard to reach']])->assertSessionHas('status');
        $this->assertSame(350, $this->row('Izzi Ward 01')['priority']);
        $this->assertSame('Hard to reach', Intelligence::tag('izzi'));

        $this->actingAs(User::factory()->lgaLeader($this->lga('Izzi')->id)->create());
        $this->get('/presets')->assertForbidden();
    }

    public function test_the_week_trend_compares_canvass_support_before_and_after_monday(): void
    {
        Settings::set('intel.min_sample', '10');
        $this->canvass('Izzi Ward 01', 10, 'undecided');
        DB::table('voters')->update(['captured_at' => now()->subWeeks(2)]);
        $this->canvass('Izzi Ward 01', 10, 'strong');

        $row = $this->row('Izzi Ward 01');
        $this->assertSame(75.0, $row['share']);
        $this->assertSame(25.0, $row['trend']);
    }

    public function test_segments_count_groups_and_never_list_people(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create();
        $this->syncVoters($agent, [
            $this->voterPayload($ward->id, ['name' => 'Farmer One', 'phone' => null, 'occupation' => 'farmer', 'age_band' => '25-34']),
            $this->voterPayload($ward->id, ['name' => 'Trader One', 'phone' => null, 'occupation' => 'trader', 'age_band' => '25-34']),
        ]);

        $this->actingAs(User::factory()->strategist()->create());
        $this->get('/segments?occupation[]=farmer')->assertOk()->assertSee('Occupation: Farmer')->assertDontSee('Farmer One')->assertSee('Small sample');
        $this->post('/segments', ['name' => 'Young farmers', 'filters' => ['occupation' => ['farmer', 'spaceman'], 'age_band' => ['25-34']]])->assertRedirect();
        $this->assertSame(['age_band' => ['25-34'], 'occupation' => ['farmer']], Segment::sole()->filters);

        $this->actingAs(User::factory()->coordinator($this->ward('Abakaliki Ward 01'))->create());
        $this->get('/segments?occupation[]=farmer')->assertOk()->assertSee('>0<', false);
    }

    public function test_a_ward_boundary_geojson_draws_the_ward_map(): void
    {
        Storage::fake('local');
        $square = fn ($x, $y) => [[[$x, $y], [$x + 0.1, $y], [$x + 0.1, $y + 0.1], [$x, $y + 0.1], [$x, $y]]];
        $geojson = json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['wardname' => 'Izzi Ward 01', 'lganame' => 'Izzi', 'statename' => 'Ebonyi'], 'geometry' => ['type' => 'Polygon', 'coordinates' => $square(8.1, 6.4)]],
            ['type' => 'Feature', 'properties' => ['wardname' => 'IZZI WARD 02', 'lganame' => 'izzi', 'statename' => 'Ebonyi'], 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [$square(8.2, 6.4)]]],
            ['type' => 'Feature', 'properties' => ['wardname' => 'Elsewhere', 'lganame' => 'Enugu North', 'statename' => 'Enugu'], 'geometry' => ['type' => 'Polygon', 'coordinates' => $square(7.4, 6.4)]],
        ]]);

        $this->actingAs(User::factory()->admin()->create());
        $this->post('/system/ward-map', ['file' => UploadedFile::fake()->createWithContent('wards.geojson', $geojson), 'attribution' => 'Test shapes'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, '2 wards matched') && str_contains($m, '2 register wards have no shape'));

        $map = app(WardMap::class)->load();
        $this->assertCount(2, $map['wards']);
        $this->assertStringStartsWith('0 0 1000 ', $map['viewBox']);

        $this->get('/')->assertOk()->assertSee('Ebonyi by ward')->assertSee('Test shapes');
        $this->post('/system/ward-map', ['remove' => 1]);
        $this->assertNull(app(WardMap::class)->load());
    }
}
