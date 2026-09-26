<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Jobs\DraftMessage;
use App\Models\AiCall;
use App\Models\MessageDraft;
use App\Models\PolicyDocument;
use App\Models\Segment;
use App\Services\Ai\Claude;
use App\Services\Broadcasting\SmsText;
use App\Services\Segments;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The AI messaging engine: choose a segment, a goal, a channel and a
 * language; Claude drafts three variants; a person edits and approves one.
 * Nothing is sent from here.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('messages.index', [
            'drafts' => MessageDraft::query()->with(['author', 'approver'])
                ->when(array_key_exists((string) $status, MessageDraft::STATUSES), fn ($query) => $query->where('status', $status))
                ->latest()->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => MessageDraft::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'policies' => PolicyDocument::query()->where('active', true)->count(),
            'spend' => AiCall::monthSpend(),
            'budget' => Claude::budget(),
            'configured' => Claude::configured(),
        ]);
    }

    public function create(Request $request, Segments $segments): View
    {
        $segment = $request->integer('segment') ? Segment::query()->find($request->integer('segment')) : null;
        $filters = $segments->clean($segment?->filters ?? (array) $request->query('filters', []));
        $summary = $segments->describe($request->user(), $filters);

        return view('messages.create', [
            'filters' => $filters,
            'label' => $segment?->name ? $segment->name.' ('.$segments->label($filters).')' : $segments->label($filters),
            'summary' => $summary,
            'saved' => Segment::query()->latest()->limit(30)->get(),
            'segment' => $segment,
            'configured' => Claude::configured(),
            'policies' => PolicyDocument::query()->where('active', true)->count(),
            'goal' => (string) $request->query('goal', ''),
        ]);
    }

    public function store(Request $request, Segments $segments): RedirectResponse
    {
        $data = $request->validate([
            'goal' => ['required', 'string', 'min:5', 'max:500'],
            'channel' => ['required', Rule::in(array_keys(config('messaging.channels')))],
            'language' => ['required', Rule::in(array_keys(config('messaging.languages')))],
            'tone' => ['nullable', Rule::in(array_keys(config('messaging.tones')))],
            'label' => ['nullable', 'string', 'max:255'],
        ], ['goal.required' => 'Say what the message should achieve.']);

        if (! Claude::configured()) {
            return back()->withInput()->with('error', 'Add the Claude API key on the System page first.');
        }

        $filters = $segments->clean((array) $request->input('filters', []));
        $draft = MessageDraft::create([
            'filters' => $filters,
            'audience' => mb_substr(($data['label'] ?? null) ?: $segments->label($filters), 0, 255),
            'audience_size' => $segments->describe($request->user(), $filters)['count'],
            'goal' => trim($data['goal']),
            'channel' => $data['channel'],
            'language' => $data['language'],
            'tone' => $data['tone'] ?? null,
            'status' => 'queued',
            'created_by' => $request->user()->id,
        ]);

        DraftMessage::dispatch($draft->id);

        return redirect()->route('messages.show', $draft);
    }

    public function show(MessageDraft $draft): View
    {
        $draft->load(['author', 'approver', 'aiCall']);

        return view('messages.show', [
            'draft' => $draft,
            'variants' => collect($draft->variants ?? [])->map(fn ($variant) => $variant + ['sms' => SmsText::describe($variant['text'])]),
            'canBroadcast' => $draft->status === 'approved' && $draft->channel === 'sms' && request()->user()->role->value !== 'lga_leader',
        ]);
    }

    /** Polled while Claude is drafting. */
    public function status(MessageDraft $draft): JsonResponse
    {
        return response()->json(['status' => $draft->status, 'done' => $draft->status !== 'queued']);
    }

    public function approve(Request $request, MessageDraft $draft): RedirectResponse
    {
        abort_unless(in_array($draft->status, ['ready', 'approved'], true), 409, 'This draft can’t be approved.');

        $data = $request->validate([
            'chosen' => ['required', 'integer', 'min:0', 'max:'.max(0, count($draft->variants ?? []) - 1)],
            'final_text' => ['required', 'string', 'min:2', 'max:5000'],
        ], ['final_text.required' => 'The approved text can’t be empty.']);

        $draft->forceFill([
            'status' => 'approved',
            'chosen' => (int) $data['chosen'],
            'final_text' => trim($data['final_text']),
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        $edited = trim($data['final_text']) !== trim((string) Arr::get($draft->variants, $data['chosen'].'.text'));
        Audit::record('messages.approve', 'Approved a '.$draft->channelLabel().' message for '.$draft->audience.($edited ? ' (edited)' : ''), ['draft_id' => $draft->id]);

        return redirect()->route('messages.show', $draft)->with('status', 'Approved. It is saved with your name.');
    }

    public function reject(MessageDraft $draft): RedirectResponse
    {
        abort_if($draft->status === 'queued', 409);
        $draft->forceFill(['status' => 'rejected'])->save();

        return redirect()->route('messages')->with('status', 'Draft rejected.');
    }

    /** Draft again with the same brief (a new draft; the old one stays in the log). */
    public function again(Request $request, MessageDraft $draft): RedirectResponse
    {
        if (! Claude::configured()) {
            return back()->with('error', 'Add the Claude API key on the System page first.');
        }

        $copy = $draft->replicate(['variants', 'error', 'ai_call_id', 'chosen', 'final_text', 'approved_by', 'approved_at']);
        $copy->forceFill(['status' => 'queued', 'created_by' => $request->user()->id])->save();
        DraftMessage::dispatch($copy->id);

        return redirect()->route('messages.show', $copy);
    }
}
