<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Jobs\SuggestNarrativeGroups;
use App\Models\Lga;
use App\Models\Narrative;
use App\Models\NarrativeReport;
use App\Models\Ward;
use App\Services\Ai\Claude;
use App\Services\Ai\NarrativeClusterer;
use App\Services\Narratives;
use App\Services\PhotoStore;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The narratives feed: reports of what people are saying (from agents and
 * the media team), grouped into narratives by hand or from AI suggestions,
 * each with a trend line and a status.
 */
class NarrativeController extends Controller
{
    public function index(Request $request, Narratives $narratives): View
    {
        $user = $request->user();
        $status = $request->query('status');
        $list = Narrative::query()->visibleTo($user)
            ->when(array_key_exists((string) $status, config('messaging.narrative_statuses')), fn ($query) => $query->where('status', $status), fn ($query) => $query->where('status', '!=', 'closed'))
            ->withCount(['reports', 'reports as week' => fn ($query) => $query->where('seen_at', '>=', now()->subWeek())])
            ->orderByDesc('week')->orderByDesc('updated_at')->limit(60)->get();

        $inbox = NarrativeReport::query()->visibleTo($user)->whereNull('narrative_id')->with(['ward.lga', 'lga', 'photos', 'reporter'])->latest('seen_at')->limit(50)->get();
        $suggestions = NarrativeClusterer::stored();

        return view('narratives.index', [
            'narratives' => $list,
            'trends' => $narratives->trends($list->pluck('id')),
            'inbox' => $inbox,
            'status' => $status,
            'open' => Narrative::query()->visibleTo($user)->where('status', '!=', 'closed')->orderBy('title')->get(['id', 'title']),
            'suggestions' => $suggestions,
            'suggestedReports' => NarrativeReport::query()->whereIn('id', collect($suggestions['groups'])->pluck('report_ids')->flatten())->whereNull('narrative_id')->get()->keyBy('id'),
            'aiReady' => Claude::configured(),
            'lgas' => Lga::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'wards' => Ward::query()->visibleTo($user)->with('lga:id,name')->orderBy('name')->get(['id', 'name', 'lga_id']),
            'counts' => [
                'week' => NarrativeReport::query()->visibleTo($user)->where('seen_at', '>=', now()->subWeek())->count(),
                'negative' => NarrativeReport::query()->visibleTo($user)->where('seen_at', '>=', now()->subWeek())->where('tone', 'negative')->count(),
                'responding' => Narrative::query()->visibleTo($user)->where('status', 'responding')->count(),
            ],
        ]);
    }

    public function show(Request $request, Narrative $narrative, Narratives $narratives): View
    {
        abort_unless(Narrative::query()->visibleTo($request->user())->whereKey($narrative->id)->exists(), 403);

        $reports = $narrative->reports()->visibleTo($request->user())->with(['ward.lga', 'lga', 'photos', 'reporter'])->latest('seen_at')->get();

        return view('narratives.show', [
            'narrative' => $narrative,
            'reports' => $reports,
            'trend' => $narratives->trends(collect([$narrative->id]), 30)[$narrative->id],
            'bySource' => $reports->countBy('source')->sortDesc(),
            'byLga' => $reports->countBy(fn ($report) => $report->lga?->name ?? 'Statewide')->sortDesc(),
            'canDraft' => in_array($request->user()->role->value, ['admin', 'strategist'], true),
        ]);
    }

    /** The media team adds a report (with an optional screenshot). */
    public function storeReport(Request $request, PhotoStore $photos): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(config('messaging.sources')))],
            'summary' => ['required', 'string', 'min:5', 'max:2000'],
            'topic' => ['required', Rule::in(array_keys(config('messaging.narrative_topics')))],
            'tone' => ['required', Rule::in(array_keys(config('messaging.narrative_tones')))],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'ward_id' => ['nullable', 'integer'],
            'lga_id' => ['nullable', 'integer'],
            'narrative_id' => ['nullable', 'integer'],
            'photo' => ['nullable', 'file', 'max:'.config('field.photo_max_kb')],
        ]);

        $user = $request->user();
        $ward = ! empty($data['ward_id']) ? Ward::query()->visibleTo($user)->find($data['ward_id']) : null;
        $lga = $ward?->lga ?? (! empty($data['lga_id']) ? Lga::query()->visibleTo($user)->find($data['lga_id']) : null);
        abort_if(! $lga && ! $user->role->isStatewide(), 422, 'Choose an LGA in your area.');
        $narrative = ! empty($data['narrative_id']) ? Narrative::query()->visibleTo($user)->find($data['narrative_id']) : null;

        $report = NarrativeReport::create([
            'uuid' => (string) Str::uuid(),
            'narrative_id' => $narrative?->id,
            'source' => $data['source'],
            'summary' => trim($data['summary']),
            'topic' => $data['topic'],
            'tone' => $data['tone'],
            'link' => $data['link'] ?? null,
            'lga_id' => $lga?->id,
            'ward_id' => $ward?->id,
            'reported_by' => $user->id,
            'seen_at' => now(),
        ]);

        if ($request->hasFile('photo')) {
            $photos->store($request->file('photo'), $report, (string) Str::uuid(), $user);
        }

        if ($narrative) {
            $narrative->touch();
            app(Narratives::class)->checkSpike($narrative);
        }

        return back()->with('status', 'Report added.');
    }

    /** Group reports into an existing narrative or a new one. */
    public function group(Request $request, Narratives $narratives): RedirectResponse
    {
        $data = $request->validate([
            'report_ids' => ['required', 'array', 'min:1'],
            'report_ids.*' => ['integer'],
            'narrative_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'required_without:narrative_id', 'string', 'max:160'],
        ], ['report_ids.required' => 'Tick the reports to group.', 'title.required_without' => 'Give the new narrative a title.']);

        $user = $request->user();
        $reports = NarrativeReport::query()->visibleTo($user)->whereIn('id', $data['report_ids'])->get();
        abort_if($reports->isEmpty(), 422, 'No reports to group.');

        $narrative = ! empty($data['narrative_id'])
            ? Narrative::query()->visibleTo($user)->findOrFail($data['narrative_id'])
            : Narrative::create([
                'title' => trim($data['title']),
                'topic' => $reports->countBy('topic')->sortDesc()->keys()->first(),
                'tone' => $reports->countBy('tone')->sortDesc()->keys()->first(),
                'status' => 'new',
                'created_by' => $user->id,
            ]);

        NarrativeReport::query()->whereIn('id', $reports->pluck('id'))->update(['narrative_id' => $narrative->id]);
        $narrative->touch();
        $narratives->checkSpike($narrative);
        $this->forgetSuggested($reports->pluck('id')->all());

        return back()->with('status', $reports->count().' '.Str::plural('report', $reports->count()).' grouped into “'.$narrative->title.'”.');
    }

    public function ungroup(Request $request, NarrativeReport $report): RedirectResponse
    {
        abort_unless(NarrativeReport::query()->visibleTo($request->user())->whereKey($report->id)->exists(), 403);
        $report->update(['narrative_id' => null]);

        return back()->with('status', 'Report moved back to the inbox.');
    }

    public function update(Request $request, Narrative $narrative): RedirectResponse
    {
        abort_unless(Narrative::query()->visibleTo($request->user())->whereKey($narrative->id)->exists(), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'required', Rule::in(array_keys(config('messaging.narrative_statuses')))],
            'topic' => ['sometimes', 'required', Rule::in(array_keys(config('messaging.narrative_topics')))],
            'tone' => ['sometimes', 'required', Rule::in(array_keys(config('messaging.narrative_tones')))],
        ]);

        $narrative->update($data);
        if (isset($data['status'])) {
            Audit::record('narratives.status', "Marked “{$narrative->title}” as ".mb_strtolower($narrative->statusLabel()), ['narrative_id' => $narrative->id]);
        }

        return back()->with('status', 'Narrative updated.');
    }

    /** Ask Claude for grouping suggestions (runs in the background). */
    public function suggest(Request $request, NarrativeClusterer $clusterer): RedirectResponse
    {
        if (! Claude::configured()) {
            return back()->with('error', 'Add the Claude API key on the System page to get AI suggestions.');
        }

        $clusterer->store([], 'working');
        SuggestNarrativeGroups::dispatch($request->user()->id);

        return back();
    }

    public function suggestStatus(): JsonResponse
    {
        $status = NarrativeClusterer::stored()['status'];

        return response()->json(['status' => $status, 'done' => $status !== 'working']);
    }

    public function dismiss(): RedirectResponse
    {
        Settings::set(NarrativeClusterer::SETTING, null);

        return back();
    }

    /**
     * @param  list<int>  $ids
     */
    private function forgetSuggested(array $ids): void
    {
        $stored = NarrativeClusterer::stored();
        if ($stored['groups'] === []) {
            return;
        }

        $stored['groups'] = collect($stored['groups'])
            ->map(fn ($group) => [...$group, 'report_ids' => array_values(array_diff($group['report_ids'], $ids))])
            ->filter(fn ($group) => $group['report_ids'] !== [])->values()->all();
        Settings::set(NarrativeClusterer::SETTING, json_encode($stored));
    }
}
