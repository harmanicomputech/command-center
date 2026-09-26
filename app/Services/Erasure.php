<?php

namespace App\Services;

use App\Models\DataRequest;
use App\Models\Influencer;
use App\Models\SmsOptOut;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Voter;
use App\Support\Audit;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Erasing personal data (NDPA): a voter's name, number, community, notes
 * and location go; the anonymous fields (ward, age band, gender,
 * occupation, support, top issue) stay so totals and zones don't change.
 * The number is added to the SMS opt-out list (as a keyed hash) so an
 * erased person is never messaged again.
 */
class Erasure
{
    /** Voter fields that identify a person. */
    private const PERSONAL = ['name' => null, 'phone' => null, 'phone_hash' => null, 'community' => null, 'notes' => null, 'latitude' => null, 'longitude' => null, 'verification_note' => null];

    /**
     * Erase every voter record with this number. Returns how many.
     */
    public function erasePhone(string $phone, string $source): int
    {
        $hash = Phone::hash($phone);
        if ($hash === null) {
            return 0;
        }

        SmsOptOut::record($phone, $source);
        SurveyResponse::query()->where('phone_hash', $hash)->update(['phone_hash' => null]);

        return $this->eraseQuery(Voter::query()->where('phone_hash', $hash));
    }

    public function eraseVoter(Voter $voter, string $source): void
    {
        if ($voter->phone) {
            $this->erasePhone($voter->phone, $source);
        }

        $this->eraseQuery(Voter::query()->whereKey($voter->id));
    }

    /**
     * Close a request: erase (or not), and forget the number it was made with.
     */
    public function close(DataRequest $request, bool $erase, ?User $by): int
    {
        $erased = $erase && $request->phone ? $this->erasePhone($request->phone, 'data request') : 0;
        $request->forceFill(['status' => $erase ? 'done' : 'rejected', 'erased' => $erased, 'phone' => null, 'handled_by' => $by?->id, 'handled_at' => now()])->save();
        Audit::record('privacy.request', ($erase ? 'Erased' : 'Closed without erasing').' a “delete my data” request #'.$request->id, ['request_id' => $request->id], rows: $erased);

        return $erased;
    }

    /**
     * The retention date: voter personal data is erased from this day
     * (null when retention is switched off with 0 days).
     */
    public static function retentionDate(): ?Carbon
    {
        $days = Settings::int('privacy.retention_days');

        return $days > 0 ? Carbon::parse(config('campaign.election_date'), config('campaign.timezone'))->addDays($days)->startOfDay() : null;
    }

    /**
     * The retention switch: once the date has passed, erase voter personal
     * data in batches (the background runner calls this until none is left),
     * plus influencer contact numbers and open requests' numbers.
     *
     * @return int records erased in this batch
     */
    public function applyRetention(int $batch = 5000): int
    {
        $date = self::retentionDate();
        if ($date === null || now()->lt($date)) {
            return 0;
        }

        $ids = Voter::query()->whereNull('erased_at')->orderBy('id')->limit($batch)->pluck('id');
        $erased = $ids->isEmpty() ? 0 : $this->eraseQuery(Voter::query()->whereIn('id', $ids));

        if ($erased < $batch) {
            Influencer::query()->whereNotNull('contact_phone')->update(['contact_phone' => null]);
            DataRequest::query()->whereNotNull('phone')->update(['phone' => null]);
            SurveyResponse::query()->whereNotNull('phone_hash')->update(['phone_hash' => null]);
            if (! Settings::get('privacy.retention_done_at')) {
                Settings::set('privacy.retention_done_at', now()->toIso8601String());
            }
        }

        if ($erased > 0) {
            Audit::record('privacy.retention', 'Retention: erased personal data from '.number_format($erased).' voter records', actor: 'System', rows: $erased);
        }

        return $erased;
    }

    private function eraseQuery($query): int
    {
        return DB::transaction(fn () => $query->whereNull('erased_at')->update([...self::PERSONAL, 'duplicate_of' => null, 'erased_at' => now(), 'updated_at' => now()]));
    }
}
