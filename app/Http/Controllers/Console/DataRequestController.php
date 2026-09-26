<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\Erasure;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Delete my data" requests: the admin calls the number to confirm, then
 * erases (or closes the request). SMS "DELETE" requests are erased at once
 * and only listed here.
 */
class DataRequestController extends Controller
{
    public function index(): View
    {
        return view('data-requests.index', [
            'pending' => DataRequest::query()->where('status', 'pending')->oldest()->get(),
            'closed' => DataRequest::query()->where('status', '!=', 'pending')->with('handler')->latest('handled_at')->limit(30)->get(),
            'retention' => Erasure::retentionDate(),
            'retentionDone' => Settings::get('privacy.retention_done_at'),
        ]);
    }

    /** Recorded by staff, e.g. a request made at a campaign office. */
    public function store(Request $request, Erasure $erasure): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:500']]);
        $phone = Phone::normalize($data['phone']);
        if ($phone === null) {
            return back()->withErrors(['phone' => 'Enter a Nigerian mobile number, e.g. 0803 123 4567.']);
        }

        $dataRequest = DataRequest::create(['channel' => 'staff', 'phone' => $phone, 'phone_hash' => Phone::hash($phone), 'note' => $data['note'] ?? null]);
        $erased = $erasure->close($dataRequest, true, $request->user());

        return back()->with('status', $erased ? "Erased {$erased} ".($erased === 1 ? 'record' : 'records').'.' : 'No registration with that number; it is on the SMS opt-out list now.');
    }

    public function erase(Request $request, DataRequest $dataRequest, Erasure $erasure): RedirectResponse
    {
        abort_unless($dataRequest->status === 'pending', 409);
        $erased = $erasure->close($dataRequest, true, $request->user());

        return back()->with('status', $erased ? "Erased {$erased} ".($erased === 1 ? 'record' : 'records').'.' : 'Nothing to erase for that number; it is on the SMS opt-out list now.');
    }

    public function reject(Request $request, DataRequest $dataRequest, Erasure $erasure): RedirectResponse
    {
        abort_unless($dataRequest->status === 'pending', 409);
        $erasure->close($dataRequest, false, $request->user());

        return back()->with('status', 'Request closed. The number has been forgotten.');
    }
}
