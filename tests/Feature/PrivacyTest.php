<?php

namespace Tests\Feature;

use App\Http\Controllers\SmsCallbackController;
use App\Models\AuditLog;
use App\Models\DataRequest;
use App\Models\Influencer;
use App\Models\SmsOptOut;
use App\Models\User;
use App\Models\Voter;
use App\Services\Erasure;
use App\Services\Segments;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;
use ZipArchive;

/**
 * Phase 8 data protection: delete-my-data (web, SMS, staff), the
 * retention switch, and the encrypted backup.
 */
class PrivacyTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
        $ward = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($ward)->create();
        $this->syncVoters($agent, [
            $this->voterPayload($ward->id, ['name' => 'First Person', 'phone' => '0800 000 0101', 'community' => 'Onu']),
            $this->voterPayload($ward->id, ['name' => 'Second Person', 'phone' => '0800 000 0102']),
        ])->assertOk();
        auth()->logout();
    }

    private function segmentCount(): int
    {
        return app(Segments::class)->describe(User::factory()->admin()->make(), [])['count'];
    }

    public function test_a_web_request_is_checked_by_phone_before_anything_is_erased(): void
    {
        $this->post('/privacy/delete', ['phone' => '12345'])->assertSessionHasErrors('phone');
        $this->post('/privacy/delete', ['phone' => '0800 000 0101', 'website' => 'spam'])->assertSessionHas('requested');
        $this->assertSame(0, DataRequest::query()->count(), 'The honeypot catches bots.');

        $this->post('/privacy/delete', ['phone' => '0800 000 0101', 'name' => 'First Person'])->assertSessionHas('requested');
        $this->post('/privacy/delete', ['phone' => '+234 800 000 0101']);
        $request = DataRequest::sole();
        $this->assertSame('pending', $request->status);
        $this->assertNull(Voter::query()->where('name', 'First Person')->value('erased_at'), 'Nothing erased yet.');

        $before = $this->segmentCount();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/data-requests')->assertOk()->assertSee('0800 000 0101')->assertSee('1 registration with this number');
        $this->post("/data-requests/{$request->id}/erase")->assertSessionHas('status', 'Erased 1 record.');

        $voter = Voter::query()->whereNotNull('erased_at')->sole();
        $this->assertSame([null, null, null, null], [$voter->name, $voter->phone, $voter->phone_hash, $voter->community]);
        $this->assertSame('leaning', $voter->support_level, 'Anonymous fields stay.');
        $this->assertSame($before, $this->segmentCount(), 'Counts are kept.');
        $this->assertTrue(SmsOptOut::has(Phone::hash('08000000101')), 'Never messaged again.');
        $request->refresh();
        $this->assertSame(['done', 1, null], [$request->status, $request->erased, $request->phone]);
        $this->assertSame(1, AuditLog::query()->where('action', 'privacy.request')->value('rows'));
        $this->get('/data-requests')->assertDontSee('0800 000 0101');

        // Only admins handle requests.
        $this->actingAs(User::factory()->strategist()->create())->get('/data-requests')->assertForbidden();
    }

    public function test_sms_delete_erases_at_once_and_admins_can_erase_a_registration(): void
    {
        $this->post('/api/sms/inbox/'.SmsCallbackController::token(), ['from' => '+2348000000102', 'text' => 'delete my data'])->assertOk();
        $this->assertNotNull(Voter::query()->whereNull('name')->value('erased_at'));
        $this->assertSame('sms', DataRequest::sole()->channel);

        $first = Voter::query()->where('name', 'First Person')->sole();
        $this->actingAs(User::factory()->coordinator($this->ward('Izzi Ward 01'))->create())->post("/voters/{$first->id}/erase")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post("/voters/{$first->id}/erase")->assertSessionHas('status');
        $this->assertSame(2, Voter::query()->whereNotNull('erased_at')->count());
        $this->get('/voters')->assertOk()->assertDontSee('First Person');
    }

    public function test_the_retention_switch_erases_personal_data_after_the_election(): void
    {
        Influencer::create(['ward_id' => $this->ward('Izzi Ward 01')->id, 'kind' => 'church', 'name' => 'A parish', 'contact_phone' => '0800 000 3001', 'relationship' => 'ally']);
        $this->assertSame('2027-05-07', Erasure::retentionDate()->toDateString(), '90 days after 6 Feb 2027.');
        $this->get('/privacy')->assertSee('7 May 2027');

        $this->artisan('privacy:retention')->assertSuccessful();
        $this->assertSame(0, Voter::query()->whereNotNull('erased_at')->count(), 'Nothing before the date.');

        $this->travelTo(now()->setDate(2027, 5, 8));
        $this->assertSame(1, app(Erasure::class)->applyRetention(batch: 1));
        $this->assertNull(Settings::get('privacy.retention_done_at'), 'More to do.');
        $this->artisan('privacy:retention')->assertSuccessful();
        $this->assertSame(2, Voter::query()->whereNotNull('erased_at')->count());
        $this->assertNull(Influencer::sole()->contact_phone);
        $this->assertNotNull(Settings::get('privacy.retention_done_at'));
        $this->assertSame(2, Voter::query()->counted()->count(), 'The anonymous records remain.');

        Settings::set('privacy.retention_days', '0');
        $this->assertNull(Erasure::retentionDate(), '0 switches it off.');
    }

    public function test_the_backup_is_an_encrypted_zip_without_passwords(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/system/backup', ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');

        $password = 'a-long-backup-passphrase';
        $response = $this->post('/system/backup', ['password' => $password, 'password_confirmation' => $password]);
        $response->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertFalse($zip->getFromName('voters.csv'), 'Unreadable without the password.');
        $zip->setPassword($password);
        $voters = $zip->getFromName('voters.csv');
        $users = $zip->getFromName('users.csv');
        $zip->close();

        $this->assertStringContainsString('First Person', $voters);
        $this->assertStringNotContainsString('0800 000 0101', $voters, 'Phones stay encrypted.');
        $this->assertStringNotContainsString('password', strtok($users, "\n"));
        $this->assertSame(AuditLog::query()->where('action', 'system.backup')->value('rows') > 0, true);
    }
}
