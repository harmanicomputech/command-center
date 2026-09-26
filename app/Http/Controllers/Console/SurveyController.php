<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\Survey;
use App\Models\Ward;
use App\Services\SurveyResults;
use App\Support\Audit;
use App\Support\SurveyPoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Building surveys, launching and closing them, and reading the results.
 * Questions can change only while a survey is a draft.
 */
class SurveyController extends Controller
{
    public function index(Request $request): View
    {
        return view('surveys.index', [
            'surveys' => Survey::query()->withCount('responses')->with('author')->latest()->get(),
            'canEdit' => $this->canEdit($request),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->canEdit($request), 403);

        return view('surveys.form', ['survey' => null, 'lgas' => Lga::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canEdit($request), 403);
        $survey = DB::transaction(function () use ($request) {
            $survey = Survey::create([...$this->validated($request), 'status' => Survey::DRAFT, 'web_token' => Str::random(24), 'created_by' => $request->user()->id]);
            $this->saveQuestions($survey, $request);

            return $survey;
        });
        Audit::record('surveys.create', "Created the survey “{$survey->title}”", ['survey_id' => $survey->id]);

        return redirect()->route('surveys.show', $survey)->with('status', 'Survey saved as a draft. Launch it when it’s ready.');
    }

    public function edit(Request $request, Survey $survey): View
    {
        abort_unless($this->canEdit($request) && $survey->status === Survey::DRAFT, 403, 'Only drafts can be edited.');

        return view('surveys.form', ['survey' => $survey->load('questions'), 'lgas' => Lga::query()->orderBy('name')->get()]);
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        abort_unless($this->canEdit($request) && $survey->status === Survey::DRAFT, 403, 'Only drafts can be edited.');
        DB::transaction(function () use ($request, $survey) {
            $survey->update($this->validated($request, $survey));
            $survey->questions()->delete();
            $this->saveQuestions($survey, $request);
        });

        return redirect()->route('surveys.show', $survey)->with('status', 'Survey saved.');
    }

    public function status(Request $request, Survey $survey): RedirectResponse
    {
        abort_unless($this->canEdit($request), 403);
        $status = $request->validate(['status' => ['required', Rule::in([Survey::LIVE, Survey::CLOSED])]])['status'];
        abort_if($status === Survey::LIVE && $survey->questions()->doesntExist(), 422, 'Add a question first.');

        $survey->update(['status' => $status]);
        Audit::record('surveys.status', ($status === Survey::LIVE ? 'Launched' : 'Closed')." the survey “{$survey->title}”", ['survey_id' => $survey->id]);

        return back()->with('status', $status === Survey::LIVE ? 'The survey is live.' : 'The survey is closed.');
    }

    public function show(Request $request, Survey $survey, SurveyResults $results): View
    {
        $survey->load('questions');
        $responses = $results->responses($survey, $request->user());
        $breakdownBy = array_key_exists((string) $request->query('by'), SurveyResults::BREAKDOWNS) ? $request->query('by') : 'lga_id';
        $question = $request->integer('question') ?: $survey->questions->first(fn ($q) => $q->type !== 'text')?->id;

        return view('surveys.show', [
            'survey' => $survey,
            'summary' => $results->summary($survey, $responses),
            'quotas' => $survey->quota_per_ward ? $results->quotas($survey, $responses) : collect(),
            'breakdown' => $question ? $results->breakdown($survey, $responses, $question, $breakdownBy) : [],
            'breakdownBy' => $breakdownBy,
            'breakdownQuestion' => $survey->questions->firstWhere('id', $question),
            'webUrl' => route('survey.public', $survey->web_token),
            'poll' => [
                'ussd' => route('poll.ussd', SurveyPoll::token()),
                'sms' => route('poll.sms', SurveyPoll::token()),
                'isUssdSurvey' => SurveyPoll::ussdSurveyId() === $survey->id,
            ],
            'canEdit' => $this->canEdit($request),
        ]);
    }

    /**
     * Which live survey the USSD poll code runs (one at a time).
     */
    public function ussd(Request $request, Survey $survey): RedirectResponse
    {
        abort_unless($this->canEdit($request), 403);
        SurveyPoll::setUssdSurvey($survey->id);

        return back()->with('status', 'The USSD poll now runs this survey.');
    }

    /**
     * Anonymous CSV of the answers (no phone numbers or names), audited.
     */
    public function export(Request $request, Survey $survey, SurveyResults $results): StreamedResponse
    {
        $survey->load('questions');
        $responses = $results->responses($survey, $request->user());
        Audit::record('surveys.export', "Exported the answers to “{$survey->title}”", ['survey_id' => $survey->id], rows: $responses->count());
        $wards = Ward::query()->with('lga')->get()->keyBy('id');

        return response()->streamDownload(function () use ($survey, $responses, $wards) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['response', 'channel', 'lga', 'ward', 'gender', 'age_band', 'occupation', 'answered_at', ...$survey->questions->pluck('prompt')->all()], escape: '\\');
            foreach ($responses as $response) {
                $ward = $wards[$response->ward_id] ?? null;
                fputcsv($out, [
                    $response->id, $response->channel, $ward?->lga?->name, $ward?->name, $response->gender, $response->age_band, $response->occupation, $response->answered_at?->toIso8601String(),
                    ...$survey->questions->map(function ($question) use ($response) {
                        $value = $response->answers[(string) $question->id] ?? null;
                        $choices = $question->choices();

                        return $value === null ? '' : implode('; ', array_map(fn ($v) => $choices[$v] ?? $v, (array) $value));
                    })->all(),
                ], escape: '\\');
            }
            fclose($out);
        }, Str::slug($survey->title).'-answers.csv', ['Content-Type' => 'text/csv']);
    }

    private function canEdit(Request $request): bool
    {
        return $request->user()->hasRole(UserRole::Admin, UserRole::Strategist, UserRole::LgaLeader);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Survey $survey = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'lga_ids' => ['array'],
            'lga_ids.*' => ['integer', 'exists:lgas,id'],
            'quota_per_ward' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(array_keys(config('surveys.channels')))],
            'sms_keyword' => ['nullable', 'alpha_num', 'max:20', Rule::unique('surveys', 'sms_keyword')->ignore($survey)],
            'questions' => ['required', 'json'],
        ]);

        // LGA leaders can only survey their own LGA.
        $user = $request->user();
        if ($user->role === UserRole::LgaLeader) {
            $data['lga_ids'] = [$user->lga_id];
        }

        return [
            'title' => $data['title'],
            'intro' => $data['intro'] ?? null,
            'lga_ids' => array_values(array_map('intval', $data['lga_ids'] ?? [])) ?: null,
            'quota_per_ward' => $data['quota_per_ward'] ?? null,
            'channels' => array_values($data['channels']),
            'sms_keyword' => filled($data['sms_keyword'] ?? null) ? strtoupper($data['sms_keyword']) : null,
        ];
    }

    private function saveQuestions(Survey $survey, Request $request): void
    {
        $questions = json_decode((string) $request->input('questions'), true) ?: [];
        $types = array_keys(config('surveys.types'));
        $position = 0;

        foreach (array_slice($questions, 0, 40) as $question) {
            $type = in_array($question['type'] ?? '', $types, true) ? $question['type'] : null;
            $prompt = trim((string) ($question['prompt'] ?? ''));
            $options = array_values(array_filter(array_map(fn ($option) => mb_substr(trim((string) $option), 0, 120), (array) ($question['options'] ?? []))));

            if ($type === null || $prompt === '') {
                continue;
            }
            if (in_array($type, ['single', 'multiple'], true) && count($options) < 2) {
                throw ValidationException::withMessages(['questions' => "“{$prompt}” needs at least two choices."]);
            }

            $survey->questions()->create([
                'position' => ++$position,
                'type' => $type,
                'prompt' => mb_substr($prompt, 0, 255),
                'options' => in_array($type, ['single', 'multiple'], true) ? array_slice($options, 0, 12) : null,
                'required' => (bool) ($question['required'] ?? true),
            ]);
        }

        if ($position === 0) {
            throw ValidationException::withMessages(['questions' => 'Add at least one question.']);
        }
    }
}
