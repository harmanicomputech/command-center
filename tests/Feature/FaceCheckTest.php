<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use App\Models\Voter;
use App\Services\Erasure;
use App\Services\Points;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

/**
 * The face check on voter registration: the phone runs the liveness prompts
 * and sends the outcome with the record, then the photo. Points before
 * verification need a passed check and its photo.
 */
class FaceCheckTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->loadSmallRegister();
        $this->agent = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
    }

    /** Sync one registration without the helper's shortcut for the photo. */
    private function register(array $overrides = [], ?User $agent = null): string
    {
        $uuid = (string) Str::uuid();
        $this->actingAs($agent ?? $this->agent)->postJson('/api/field/sync', ['items' => [[
            'id' => $uuid, 'type' => 'voter', 'payload' => $this->voterPayload($this->ward('Izzi Ward 01')->id, ['phone' => '0800 000 '.random_int(1000, 9999), ...$overrides]),
        ]]])->assertOk()->assertJsonPath('results.0.status', 'ok');

        return $uuid;
    }

    private function sendFace(string $voterUuid, ?User $agent = null)
    {
        return $this->actingAs($agent ?? $this->agent)->post('/api/field/photos', [
            'uuid' => (string) Str::uuid(), 'owner_type' => 'voter', 'owner_uuid' => $voterUuid, 'photo' => UploadedFile::fake()->image('face.jpg', 480, 480),
        ]);
    }

    private function points(): int
    {
        return app(Points::class)->totals([$this->agent->id])[$this->agent->id]['points'] ?? 0;
    }

    public function test_a_passed_face_check_earns_points_once_its_photo_arrives(): void
    {
        $uuid = $this->register();
        $voter = Voter::query()->where('uuid', $uuid)->first();

        $this->assertSame('passed', $voter->face_status);
        $this->assertSame(['smile', 'turn', 'blink'], $voter->face_check['challenges']);
        $this->assertSame(config('field.face_engine.version'), $voter->face_check['engine']);
        $this->assertSame(0, $this->points(), 'No points until the photo is here.');

        $this->sendFace($uuid)->assertOk();
        $this->assertNotNull($voter->fresh()->face_photo_at);
        $this->assertSame(3, $this->points());
        Storage::disk('local')->assertExists($voter->facePhoto->path);

        // A retake replaces the photo: one face per registration.
        $old = $voter->facePhoto;
        $this->sendFace($uuid)->assertOk();
        $this->assertSame(1, Photo::query()->where('owner_type', 'voter')->where('owner_id', $voter->id)->count());
        Storage::disk('local')->assertMissing($old->path);
    }

    public function test_a_photo_only_registration_counts_once_verified(): void
    {
        $uuid = $this->register(['face_check' => ['status' => 'photo_only', 'reason' => 'This phone is too old for the face check.']]);
        $this->sendFace($uuid)->assertOk();
        $voter = Voter::query()->where('uuid', $uuid)->first();

        $this->assertSame('photo_only', $voter->face_status);
        $this->assertSame('This phone is too old for the face check.', $voter->face_check['reason']);
        $this->assertSame(0, $this->points());

        $voter->update(['status' => Voter::VERIFIED]);
        $this->assertSame(10, $this->points());
    }

    public function test_a_check_that_skipped_prompts_is_not_a_pass(): void
    {
        $uuid = $this->register(['face_check' => ['status' => 'passed', 'challenges' => ['smile', 'smile', 'blink']]]);
        $this->sendFace($uuid)->assertOk();

        $this->assertSame('photo_only', Voter::query()->where('uuid', $uuid)->value('face_status'));
        $this->assertSame(0, $this->points());
    }

    public function test_a_registration_without_a_face_check_is_kept_but_earns_nothing(): void
    {
        $uuid = $this->register(['face_check' => null]);

        $voter = Voter::query()->where('uuid', $uuid)->first();
        $this->assertSame('missing', $voter->face_status);
        $this->assertSame(0, $this->points());

        $coordinator = User::factory()->coordinator($this->ward('Izzi Ward 01'))->create();
        $this->actingAs($coordinator)->get('/voters?filter=face')->assertOk()->assertSee('No face check')->assertSee($voter->name);
    }

    public function test_made_up_face_check_data_is_refused(): void
    {
        $this->actingAs($this->agent)->postJson('/api/field/sync', ['items' => [[
            'id' => (string) Str::uuid(), 'type' => 'voter', 'payload' => $this->voterPayload($this->ward('Izzi Ward 01')->id, ['face_check' => ['status' => 'passed', 'challenges' => ['wink']]]),
        ]]])->assertOk()->assertJsonPath('results.0.status', 'invalid');

        $this->assertSame(0, Voter::query()->count());
    }

    public function test_with_the_check_off_registrations_need_no_face(): void
    {
        $this->actingAs($this->agent)->get('/field/register')->assertOk()->assertSee('Start face check')->assertSee('a photo of my face');

        Settings::set('canvass.face_check', 'off');
        $this->actingAs($this->agent)->get('/field/register')->assertOk()->assertDontSee('Start face check');

        $uuid = $this->register(['face_check' => null]);
        $this->assertSame('not_required', Voter::query()->where('uuid', $uuid)->value('face_status'));
        $this->assertSame(3, $this->points());
    }

    public function test_only_the_agent_who_registered_the_voter_sends_its_face(): void
    {
        // Not synced yet: the phone tries again later.
        $this->sendFace((string) Str::uuid())->assertStatus(409);

        $uuid = $this->register();
        $other = User::factory()->agent($this->ward('Izzi Ward 01'))->create();
        $this->sendFace($uuid, $other)->assertForbidden();
        $this->assertNull(Voter::query()->where('uuid', $uuid)->value('face_photo_at'));
    }

    public function test_face_photos_are_seen_by_staff_in_the_area_only(): void
    {
        $uuid = $this->register();
        $this->sendFace($uuid)->assertOk();
        $voter = Voter::query()->where('uuid', $uuid)->first();
        $photo = $voter->facePhoto;

        $coordinator = User::factory()->coordinator($this->ward('Izzi Ward 01'))->create();
        $this->actingAs($coordinator)->get($photo->url(true))->assertOk();
        $this->actingAs($coordinator)->get('/voters')->assertOk()->assertSee($photo->url(true), false)->assertSee('Face check passed');
        $this->actingAs($coordinator)->get(route('people.show', $this->agent))->assertOk()->assertSee('Recent face photos');

        // Not the agent who took it, and not staff elsewhere.
        $this->actingAs($this->agent)->get($photo->url())->assertForbidden();
        $elsewhere = User::factory()->lgaLeader($this->lga('Abakaliki')->id)->create();
        $this->actingAs($elsewhere)->get($photo->url())->assertForbidden();
    }

    public function test_erasing_a_voter_deletes_their_face_photo(): void
    {
        $uuid = $this->register();
        $this->sendFace($uuid)->assertOk();
        $voter = Voter::query()->where('uuid', $uuid)->first();
        $path = $voter->facePhoto->path;

        app(Erasure::class)->eraseVoter($voter, 'test');

        $this->assertSame(0, Photo::query()->where('owner_type', 'voter')->count());
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($voter->fresh()->face_check);

        // A photo still queued on the phone is not stored for an erased record.
        $this->sendFace($uuid)->assertOk();
        $this->assertSame(0, Photo::query()->where('owner_type', 'voter')->count());
    }

    public function test_admins_switch_the_check_off_in_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/settings')->assertOk()->assertSee('Face check');

        $this->actingAs($admin)->put('/settings/registration', ['canvass__face_check' => 'sometimes'])->assertSessionHasErrors('canvass__face_check');
        $this->actingAs($admin)->put('/settings/registration', ['canvass__face_check' => 'off'])->assertRedirect();
        $this->assertSame('off', Settings::get('canvass.face_check'));
    }

    public function test_the_privacy_notice_explains_the_face_photo(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('A photo of your face')->assertSee('never measurements of your face');
    }
}
