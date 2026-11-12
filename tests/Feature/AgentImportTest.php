<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ward;
use App\Services\WardMatcher;
use App\Services\WardNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

/**
 * Enrolling field agents from a list: name and phone, the last 4 digits as
 * the first PIN, the ward matched by name when it can be.
 */
class AgentImportTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
        $this->admin = User::factory()->admin()->create();
    }

    private function import(string $csv, bool $mustChangePin = true)
    {
        return $this->actingAs($this->admin)->post(route('team.import'), [
            'file' => UploadedFile::fake()->createWithContent('agents.csv', $csv),
            'must_change_pin' => $mustChangePin ? '1' : '0',
        ]);
    }

    private function signIn(string $phone, string $pin)
    {
        auth()->logout();

        return $this->post('/login', ['login' => $phone, 'password' => $pin]);
    }

    public function test_agents_are_enrolled_with_the_last_4_digits_as_their_pin(): void
    {
        app(WardNames::class)->rename($this->ward('Izzi Ward 01'), 'Agbaja');

        $this->import("name,phone,lga,ward\n"
            ."ADA   NWEKE,0800 000 1234,Izzi,AGBAJA WARD\n"     // ward matches by name
            ."Obi Eze,08000005678,Izzi,ISHIEKE WARD 2\n")        // no such ward yet
            ->assertRedirect(route('team'))->assertSessionHas('imported', fn ($result) => $result['created'] === 2 && $result['placed'] === 1);

        $ada = User::query()->where('phone', '+2348000001234')->first();
        $this->assertSame(['Ada Nweke', 'agent', 'Agbaja', true], [$ada->name, $ada->role->value, $ada->ward->name, $ada->must_change_pin]);
        $obi = User::query()->where('phone', '+2348000005678')->first();
        $this->assertNull($obi->ward_id);
        $this->assertSame([$this->lga('Izzi')->id, 'ISHIEKE WARD 2'], [$obi->lga_id, $obi->ward_label]);

        // The last 4 digits sign them in; the light bulk hash is upgraded there.
        $this->signIn('08000001234', '1234')->assertRedirect(route('field.home'));
        $this->assertFalse(Hash::needsRehash($ada->fresh()->password));
    }

    public function test_they_choose_their_own_pin_before_using_the_app(): void
    {
        $this->import("name,phone,lga\nAda Nweke,08000001234,Izzi\n");
        $ada = User::query()->where('phone', '+2348000001234')->first();
        $this->signIn('08000001234', '1234');

        $this->get('/field')->assertRedirect(route('field.me', ['pin' => 1]));
        $this->get('/field/register')->assertRedirect(route('field.me', ['pin' => 1]));
        $this->get(route('field.me', ['pin' => 1]))->assertOk()->assertSee('Choose your own PIN');

        // Work already on the phone still reaches the server (here: an empty batch is refused as such).
        $this->postJson('/api/field/sync', ['items' => []])->assertStatus(422);

        // Not the end of the phone number again.
        $this->put(route('account.password'), ['current_password' => '1234', 'password' => '1234', 'password_confirmation' => '1234'])->assertSessionHasErrors('password');
        $this->put(route('account.password'), ['current_password' => '1234', 'password' => '8642', 'password_confirmation' => '8642'])->assertSessionHasNoErrors();

        $this->assertFalse($ada->fresh()->must_change_pin);
        $this->get('/field')->assertOk();
        $this->signIn('08000001234', '8642')->assertRedirect(route('field.home'));
    }

    public function test_the_pin_change_can_be_left_out(): void
    {
        $this->import("name,phone,lga\nAda Nweke,08000001234,Izzi\n", mustChangePin: false);
        $this->signIn('08000001234', '1234');

        $this->get('/field')->assertOk();
    }

    public function test_doubtful_rows_are_skipped_not_guessed(): void
    {
        User::factory()->agent($this->ward('Izzi Ward 01'))->create(['phone' => '+2348000009999']);

        $this->import("name,phone,lga,ward\n"
            ."Good Person,08000001111,Abakaliki,\n"
            ."Short Number,0800000111,Izzi,\n"
            .",08000002222,Izzi,\n"
            ."No Phone,,Izzi,\n"
            ."Shared One,08000003333,Izzi,\n"
            ."Shared Two,08000003333,Izzi,\n"
            ."Already Here,08000009999,Izzi,\n"
            ."Wrong Lga,08000004444,Lagos Island,\n")
            ->assertSessionHas('imported', function ($result) {
                $reasons = collect($result['skipped'])->pluck('reason', 'line');

                return $result['created'] === 1 && $result['skippedTotal'] === 7
                    && str_starts_with($reasons[3], 'Not a Nigerian mobile') && $reasons[4] === 'No name' && $reasons[5] === 'No phone number'
                    && str_contains($reasons[6], '2 rows') && str_contains($reasons[7], '2 rows')
                    && $reasons[8] === 'This number already has an account' && str_starts_with($reasons[9], 'Unknown LGA');
            });

        $this->assertSame(1, User::query()->where('name', 'Good Person')->count());
        $this->actingAs($this->admin)->get(route('team'))->assertOk()->assertSee('1 field agent enrolled')->assertSee('7 rows not enrolled');
    }

    public function test_naming_a_ward_later_places_the_agents_waiting_for_it(): void
    {
        $this->import("name,phone,lga,ward\nObi Eze,08000005678,Izzi,Ishieke Ward II\n");
        $obi = User::query()->where('phone', '+2348000005678')->first();
        $this->assertNull($obi->ward_id);

        app(WardNames::class)->rename($this->ward('Izzi Ward 02'), 'Ishieke 2');

        $this->assertSame($this->ward('Izzi Ward 02')->id, $obi->fresh()->ward_id);
    }

    public function test_ward_names_are_compared_loosely(): void
    {
        $this->assertSame(WardMatcher::key('ISHIEKE WARD II'), WardMatcher::key('Ishieke 2'));
        $this->assertSame(WardMatcher::key('Ward 013 – Ndiegu'), WardMatcher::key('ndiegu 13'));
        $this->assertNotSame(WardMatcher::key('Izzi Ward 01'), WardMatcher::key('Izzi Ward 10'));
    }

    public function test_leaders_see_their_lgas_unplaced_agents_and_only_admins_import(): void
    {
        $this->import("name,phone,lga,ward\nObi Eze,08000005678,Izzi,Somewhere Ward\nAda Nweke,08000001234,Abakaliki,\n");

        $leader = User::factory()->lgaLeader($this->lga('Izzi')->id)->create();
        $this->actingAs($leader)->get(route('team'))->assertOk()->assertSee('Obi Eze')->assertSee('Somewhere Ward, not matched yet')->assertDontSee('Ada Nweke');
        $this->actingAs($leader)->post(route('team.import'), ['file' => UploadedFile::fake()->createWithContent('a.csv', "name,phone,lga\nX Y,08000007777,Izzi\n")])->assertForbidden();

        $this->actingAs($this->admin)->get(route('team', ['q' => '5678']))->assertOk()->assertSee('Obi Eze')->assertDontSee('Ada Nweke');
    }

    public function test_the_team_list_comes_in_pages(): void
    {
        $ward = $this->ward('Izzi Ward 01');
        User::factory()->count(55)->agent($ward)->sequence(fn ($sequence) => ['phone' => '+234800000'.str_pad((string) $sequence->index, 4, '0', STR_PAD_LEFT), 'name' => 'Agent '.Str::padLeft((string) $sequence->index, 3, '0')])->create();

        $this->actingAs($this->admin)->get(route('team'))->assertOk()->assertSee('Agent 049')->assertDontSee('Agent 050');
        $this->actingAs($this->admin)->get(route('team', ['page' => 2]))->assertOk()->assertSee('Agent 054');
    }
}
