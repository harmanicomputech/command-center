<?php

namespace App\Http\Controllers;

use App\Models\DataRequest;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public privacy notice and its "delete my data" request form. A web
 * request erases nothing by itself: anyone could type anyone's number, so
 * the team calls the number back first.
 */
class PrivacyController extends Controller
{
    public function show(): View
    {
        return view('privacy');
    }

    public function request(Request $request): RedirectResponse
    {
        // A hidden field bots fill in.
        if (filled($request->input('website'))) {
            return back()->with('requested', true);
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:120'],
        ], ['phone.required' => 'Enter the phone number you registered with.']);

        $phone = Phone::normalize($data['phone']);
        if ($phone === null) {
            return back()->withInput()->withErrors(['phone' => 'Enter a Nigerian mobile number, e.g. 0803 123 4567.']);
        }

        $hash = Phone::hash($phone);
        DataRequest::query()->firstOrCreate(
            ['phone_hash' => $hash, 'status' => 'pending'],
            ['channel' => 'web', 'phone' => $phone, 'name' => filled($data['name'] ?? null) ? trim($data['name']) : null],
        );

        return back()->with('requested', true);
    }
}
