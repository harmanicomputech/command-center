<?php

namespace Tests\Feature;

use App\Http\Controllers\SmsCallbackController;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Models\MessageDraft;
use App\Models\SmsOptOut;
use App\Models\User;
use App\Models\Voter;
use App\Services\Broadcasting\SmsText;
use App\Support\Phone;
use App\Support\Secrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\LoadsRegister;
use Tests\Concerns\RegistersVoters;
use Tests\TestCase;

/**
 * SMS broadcasts to segments: consent, STOP opt-outs, one message per
 * number, the cost preview, delivery reports.
 */
class BroadcastTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;
    use RegistersVoters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSmallRegister();
    }

    public function test_sms_length_counts_gsm_and_unicode_parts(): void
    {
        $this->assertSame(['length' => 160, 'parts' => 1, 'unicode' => false], SmsText::describe(str_repeat('a', 160)));
        $this->assertSame(2, SmsText::parts(str_repeat('a', 161)));
        $this->assertSame(3, SmsText::parts(str_repeat('a', 307)));
        $this->assertSame(2, SmsText::length('€'));
        $this->assertTrue(SmsText::describe('Ndewo, ụmụnna anyị')['unicode']);
        $this->assertSame(2, SmsText::parts(str_repeat('ọ', 71)));
    }

    public function test_a_broadcast_reaches_consenting_numbers_once_and_skips_opt_outs(): void
    {
        Http::fake(['*africastalking.com/*' => function ($request) {
            $numbers = explode(',', $request['to']);

            return Http::response(['SMSMessageData' => ['Recipients' => array_map(fn ($number, $i) => ['number' => $number, 'statusCode' => 101, 'status' => 'Success', 'messageId' => "ATX-{$i}", 'cost' => 'NGN 4.0000'], $numbers, array_keys($numbers))]]);
        }]);
        Secrets::set('africastalking_username', 'campaign');
        Secrets::set('africastalking_api_key', 'at-test-key');

        $izzi = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($izzi)->create();
        $this->syncVoters($agent, [
            $this->voterPayload($izzi->id, ['phone' => '0800 000 0001', 'occupation' => 'farmer']),
            $this->voterPayload($izzi->id, ['phone' => '0800 000 0002', 'occupation' => 'farmer']),
            $this->voterPayload($izzi->id, ['phone' => '0800 000 0003', 'occupation' => 'farmer']),
            $this->voterPayload($izzi->id, ['phone' => null, 'occupation' => 'farmer']),
            $this->voterPayload($izzi->id, ['phone' => '0800 000 0004', 'occupation' => 'trader']),
        ])->assertOk();
        SmsOptOut::record('0800 000 0003', 'test');
        $this->assertNotNull(Voter::query()->where('phone_hash', Phone::hash('08000000003'))->value('opted_out_at'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $audience = ['type' => 'voters', 'filters' => ['occupation' => ['farmer']]];

        $this->postJson('/broadcasts/preview', ['audience' => $audience, 'message' => 'Town hall on Saturday'])
            ->assertOk()->assertJson(['count' => 2, 'parts' => 1, 'unicode' => false, 'cost' => 8.0]);

        $draft = MessageDraft::create(['audience' => 'Farmers', 'goal' => 'Invite', 'channel' => 'sms', 'language' => 'en', 'status' => 'approved', 'final_text' => 'Town hall on Saturday', 'approved_by' => $admin->id, 'approved_at' => now(), 'filters' => ['occupation' => ['farmer']]]);
        $this->get('/broadcasts/new?draft='.$draft->id)->assertOk()->assertSee('From an approved message')->assertSee('Town hall on Saturday');

        $this->post('/broadcasts', ['title' => 'Farmers', 'message' => 'Town hall on Saturday', 'audience' => $audience, 'draft_id' => $draft->id])->assertRedirect();
        $broadcast = Broadcast::sole();
        $this->assertSame('Town hall on Saturday Reply STOP to opt out', $broadcast->message);
        $this->assertSame($draft->id, $broadcast->message_draft_id);
        $this->get("/broadcasts/{$broadcast->id}")->assertOk()->assertSee('Send to 2 people now');

        $this->post("/broadcasts/{$broadcast->id}/send")->assertSessionHasErrors('confirm');
        $this->post("/broadcasts/{$broadcast->id}/send", ['confirm' => '1'])->assertRedirect();

        $broadcast->refresh();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(2, $broadcast->recipients);
        $this->assertSame(2, $broadcast->messages()->where('status', 'sent')->count());
        Http::assertSent(fn ($request) => $request['to'] === '+2348000000001,+2348000000002' && $request->hasHeader('apiKey', 'at-test-key'));
        $this->assertSame(2, AuditLog::query()->where('action', 'broadcasts.send')->value('rows'));

        // Sending twice does nothing.
        $this->post("/broadcasts/{$broadcast->id}/send", ['confirm' => '1'])->assertStatus(409);

        // Delivery reports; a wrong token is a 404.
        $token = SmsCallbackController::token();
        $this->post('/api/sms/delivery/wrong', ['id' => 'ATX-0', 'status' => 'Success'])->assertNotFound();
        $this->post("/api/sms/delivery/{$token}", ['id' => 'ATX-0', 'status' => 'Success'])->assertOk();
        $this->post("/api/sms/delivery/{$token}", ['id' => 'ATX-1', 'status' => 'Failed', 'failureReason' => 'UserInBlacklist'])->assertOk();
        $this->assertSame(['delivered' => 1, 'failed' => 1], collect($broadcast->counts())->sortKeys()->all());
        $this->get("/broadcasts/{$broadcast->id}")->assertOk()->assertSee('UserInBlacklist');
    }

    public function test_stop_replies_and_opt_out_callbacks_are_honoured(): void
    {
        $izzi = $this->ward('Izzi Ward 01');
        $agent = User::factory()->agent($izzi)->create();
        $this->syncVoters($agent, [$this->voterPayload($izzi->id, ['phone' => '0800 000 0011']), $this->voterPayload($izzi->id, ['phone' => '0800 000 0012'])])->assertOk();
        $token = SmsCallbackController::token();

        $this->post("/api/sms/inbox/{$token}", ['from' => '+2348000000011', 'text' => ' stop '])->assertOk();
        $this->post("/api/sms/inbox/{$token}", ['from' => '+2348000000012', 'text' => 'Hello'])->assertOk();
        $this->post("/api/sms/opt-out/{$token}", ['phoneNumber' => '+2348000000099'])->assertOk();

        $this->assertTrue(SmsOptOut::has(Phone::hash('08000000011')));
        $this->assertFalse(SmsOptOut::has(Phone::hash('08000000012')));
        $this->assertTrue(SmsOptOut::has(Phone::hash('08000000099')), 'Kept even with no voter record.');
        $this->assertSame(1, Voter::query()->whereNotNull('opted_out_at')->count());
        $this->assertSame(0, SmsOptOut::query()->where('phone_hash', 'like', '%0800%')->count(), 'Only hashes are stored.');
    }

    public function test_team_broadcasts_and_access(): void
    {
        $izzi = $this->ward('Izzi Ward 01');
        User::factory()->agent($izzi)->create(['phone' => '0800 000 0021']);
        User::factory()->agent($this->ward('Abakaliki Ward 01'))->create(['phone' => '0800 000 0022']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/broadcasts/preview', ['audience' => ['type' => 'team', 'roles' => ['agent'], 'lga_id' => [$izzi->lga_id]], 'message' => 'Meeting at 4'])
            ->assertOk()->assertJson(['count' => 1]);
        $this->postJson('/broadcasts/preview', ['audience' => ['type' => 'team', 'roles' => ['agent']], 'message' => 'Meeting at 4'])->assertJson(['count' => 2]);

        $this->get('/broadcasts')->assertOk();
        $this->actingAs(User::factory()->coordinator($izzi)->create())->get('/broadcasts')->assertForbidden();
        $this->actingAs(User::factory()->lgaLeader($izzi->lga_id)->create())->post('/broadcasts', ['title' => 'x', 'message' => 'y'])->assertForbidden();
        $this->assertSame(0, BroadcastMessage::query()->count());
    }
}
