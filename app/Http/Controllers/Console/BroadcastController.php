<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Models\Lga;
use App\Models\MessageDraft;
use App\Models\Segment;
use App\Services\Broadcasting\Audience;
use App\Services\Broadcasting\BroadcastDispatcher;
use App\Services\Broadcasting\SmsSender;
use App\Services\Broadcasting\SmsText;
use App\Services\Segments;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SMS broadcasts to a segment of consenting voters or to the team, with a
 * count and cost preview. Sending is a separate, confirmed, audited step.
 */
class BroadcastController extends Controller
{
    public function index(): View
    {
        $broadcasts = Broadcast::query()->with(['author', 'sender'])->latest()->paginate(20);

        return view('broadcasts.index', [
            'broadcasts' => $broadcasts,
            'counts' => BroadcastMessage::query()->whereIn('broadcast_id', $broadcasts->pluck('id'))
                ->selectRaw('broadcast_id, status, count(*) as n')->groupBy('broadcast_id', 'status')->get()
                ->groupBy('broadcast_id')->map(fn ($rows) => $rows->pluck('n', 'status')->map(fn ($n) => (int) $n)),
            'configured' => SmsSender::configured(),
        ]);
    }

    public function create(Request $request, Segments $segments, Audience $audience): View
    {
        $draft = $request->integer('draft') ? MessageDraft::query()->where('status', 'approved')->find($request->integer('draft')) : null;
        $segment = $request->integer('segment') ? Segment::query()->find($request->integer('segment')) : null;
        $filters = $segment?->filters ?? $draft?->filters ?? (array) $request->query('filters', []);
        $initial = $audience->clean(['type' => 'voters', 'filters' => $filters]);

        return view('broadcasts.create', [
            'draft' => $draft,
            'audience' => $initial,
            'audienceLabel' => $audience->label($initial),
            'count' => $audience->count($initial, $request->user()),
            'saved' => Segment::query()->latest()->limit(30)->get(),
            'lgas' => Lga::query()->orderBy('name')->get(['id', 'name']),
            'footer' => (string) Settings::get('sms.footer'),
            'costPerPart' => (float) Settings::get('sms.cost_per_part'),
            'configured' => SmsSender::configured(),
        ]);
    }

    /** Count and cost for the preview, as the form changes. */
    public function preview(Request $request, Audience $audience): JsonResponse
    {
        $clean = $audience->clean((array) $request->input('audience', []));
        $text = self::withFooter((string) $request->input('message', ''));
        $count = $audience->count($clean, $request->user());
        $sms = SmsText::describe($text);

        return response()->json([
            'count' => $count,
            'label' => $audience->label($clean),
            ...$sms,
            'cost' => round($count * $sms['parts'] * (float) Settings::get('sms.cost_per_part'), 2),
        ]);
    }

    public function store(Request $request, Audience $audience): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:2', 'max:918'],
            'draft_id' => ['nullable', 'integer'],
        ], ['message.max' => 'Keep it under 6 SMS parts.']);

        $clean = $audience->clean((array) $request->input('audience', []));
        $text = self::withFooter(trim($data['message']));
        $draft = ! empty($data['draft_id']) ? MessageDraft::query()->where('status', 'approved')->find($data['draft_id']) : null;

        $broadcast = Broadcast::create([
            'title' => $data['title'],
            'message' => $text,
            'audience' => $clean,
            'audience_label' => mb_substr($audience->label($clean), 0, 255),
            'message_draft_id' => $draft?->id,
            'status' => Broadcast::DRAFT,
            'recipients' => $audience->count($clean, $request->user()),
            'parts' => SmsText::parts($text),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('broadcasts.show', $broadcast)->with('status', 'Broadcast saved as a draft. Check it, then send.');
    }

    public function show(Request $request, Broadcast $broadcast, Audience $audience): View
    {
        $counts = $broadcast->counts();
        $recipients = $broadcast->editable() ? $audience->count($broadcast->audience, $request->user()) : $broadcast->recipients;

        return view('broadcasts.show', [
            'broadcast' => $broadcast->load(['author', 'sender', 'draft.approver']),
            'counts' => $counts,
            'recipients' => $recipients,
            'sms' => SmsText::describe($broadcast->message),
            'cost' => round($recipients * $broadcast->parts * (float) Settings::get('sms.cost_per_part'), 2),
            'failures' => $broadcast->messages()->where('status', 'failed')->selectRaw('failure_reason, count(*) as n')->groupBy('failure_reason')->orderByDesc('n')->limit(5)->pluck('n', 'failure_reason'),
            'configured' => SmsSender::configured(),
        ]);
    }

    public function send(Request $request, Broadcast $broadcast, BroadcastDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($broadcast->editable(), 409, 'This broadcast has already been sent.');

        if (! SmsSender::configured()) {
            return back()->with('error', 'Add the Africa’s Talking username and API key on the System page first.');
        }

        $request->validate(['confirm' => ['required', 'accepted']], ['confirm.accepted' => 'Tick the box to confirm.']);
        $count = $dispatcher->start($broadcast, $request->user());
        Audit::record('broadcasts.send', "Sent the SMS broadcast “{$broadcast->title}” to ".number_format($count).' people ('.$broadcast->audience_label.')', ['broadcast_id' => $broadcast->id], rows: $count);

        return redirect()->route('broadcasts.show', $broadcast)->with('status', 'Sending to '.number_format($count).' people. Delivery reports come in over the next minutes.');
    }

    public function destroy(Broadcast $broadcast): RedirectResponse
    {
        abort_unless($broadcast->editable(), 409, 'A sent broadcast stays in the log.');
        $broadcast->delete();

        return redirect()->route('broadcasts')->with('status', 'Draft deleted.');
    }

    public static function withFooter(string $message): string
    {
        $footer = trim((string) Settings::get('sms.footer'));

        return $footer === '' || str_contains(mb_strtolower($message), mb_strtolower($footer)) ? $message : rtrim($message).' '.$footer;
    }
}
