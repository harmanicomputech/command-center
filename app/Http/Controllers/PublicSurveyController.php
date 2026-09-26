<?php

namespace App\Http\Controllers;

use App\Models\Lga;
use App\Models\Survey;
use App\Services\SurveyRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The public web link for a survey (online respondents, no sign-in).
 */
class PublicSurveyController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $survey = Survey::query()->with('questions')->where('web_token', $token)->firstOrFail();

        return view('surveys.public', [
            'survey' => $survey,
            'open' => $survey->status === Survey::LIVE && $survey->hasChannel('web'),
            'done' => $request->cookie('survey_'.$survey->id) === 'done' || session('survey_done') === $survey->id,
            'lgas' => Lga::query()->with('wards')->orderBy('name')->get()
                ->filter(fn (Lga $lga) => empty($survey->lga_ids) || in_array($lga->id, array_map('intval', $survey->lga_ids), true))->values(),
        ]);
    }

    public function store(Request $request, string $token, SurveyRecorder $recorder): RedirectResponse
    {
        $survey = Survey::query()->with('questions')->where('web_token', $token)->firstOrFail();

        // A filled hidden field means a bot: pretend it worked.
        if (filled($request->input('website'))) {
            return redirect()->route('survey.public', $token)->with('survey_done', $survey->id);
        }

        $request->validate(['phone' => ['nullable', 'string', 'max:20'], 'ward_id' => ['nullable', 'integer']]);

        try {
            $recorder->record($survey, (array) $request->input('answers', []), [
                'uuid' => (string) Str::uuid(),
                'channel' => 'web',
                'ward_id' => $request->integer('ward_id') ?: null,
                'phone' => $request->input('phone'),
                'gender' => $request->input('gender'),
                'age_band' => $request->input('age_band'),
                'occupation' => $request->input('occupation'),
            ]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('survey.public', $token)->with('survey_done', $survey->id)
            ->withCookie(cookie('survey_'.$survey->id, 'done', 60 * 24 * 90));
    }
}
