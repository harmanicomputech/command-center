<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Lga;
use App\Models\User;
use App\Models\Ward;
use App\Support\Audit;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Meetings and events: plan them, then record what happened (attendance,
 * who from the team came, notes). A calendar across the user's LGAs.
 */
class EventController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->query('month'), Time::zone())->startOfMonth(), Time::now()->startOfMonth(), false);
        $gridStart = $month->copy()->startOfWeek();
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek();

        $inMonth = Event::query()->visibleTo($user)->with('lga', 'ward')
            ->whereBetween('starts_at', [$gridStart->copy()->utc(), $gridEnd->copy()->utc()])
            ->orderBy('starts_at')->get()
            ->groupBy(fn (Event $event) => $event->starts_at->copy()->setTimezone(Time::zone())->format('Y-m-d'));

        return view('events.index', [
            'month' => $month,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'byDay' => $inMonth,
            'upcoming' => Event::query()->visibleTo($user)->with('lga', 'ward')->where('starts_at', '>=', now()->subHours(3))->where('status', '!=', Event::CANCELLED)->orderBy('starts_at')->limit(8)->get(),
            'toRecord' => Event::query()->visibleTo($user)->with('lga', 'ward')->where('status', Event::PLANNED)->where('starts_at', '<', now()->subHours(3))->orderByDesc('starts_at')->limit(8)->get(),
            ...$this->formOptions($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $event = Event::create([...$this->validated($request), 'status' => Event::PLANNED, 'created_by' => $request->user()->id]);
        Audit::record('events.create', "Planned “{$event->title}” on ".Time::local($event->starts_at, 'j M'), ['event_id' => $event->id]);

        return redirect()->route('events.show', $event)->with('status', 'Event planned.');
    }

    public function show(Request $request, Event $event): View
    {
        $this->authorizeEvent($request->user(), $event);

        $team = User::query()->whereIn('role', [UserRole::WardCoordinator, UserRole::Agent])->whereNull('disabled_at')
            ->when($event->ward_id, fn ($query) => $query->where('ward_id', $event->ward_id), fn ($query) => $query->where('lga_id', $event->lga_id))
            ->orderBy('name')->get();

        return view('events.show', [
            'event' => $event->load('lga', 'ward', 'author', 'attendees'),
            'team' => $team,
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request->user(), $event);
        $event->update($this->validated($request));

        return back()->with('status', 'Event updated.');
    }

    /**
     * Record what happened: held (with attendance and who came) or cancelled.
     */
    public function record(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request->user(), $event);
        $data = $request->validate([
            'status' => ['required', Rule::in([Event::HELD, Event::CANCELLED])],
            'attendance' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attendees' => ['array'],
            'attendees.*' => ['integer'],
        ]);

        $event->update(['status' => $data['status'], 'attendance' => $data['status'] === Event::HELD ? ($data['attendance'] ?? null) : null, 'notes' => $data['notes'] ?? $event->notes]);

        // Only team members in the event's area can be marked as attending.
        $allowed = User::query()->whereIn('id', $data['attendees'] ?? [])
            ->when($event->ward_id, fn ($query) => $query->where('ward_id', $event->ward_id), fn ($query) => $query->where('lga_id', $event->lga_id))
            ->pluck('id');
        $event->attendees()->syncWithPivotValues($data['status'] === Event::HELD ? $allowed : [], ['created_at' => now()]);
        Audit::record('events.record', "Recorded “{$event->title}” as {$data['status']}", ['event_id' => $event->id, 'attendees' => $allowed->count()]);

        return back()->with('status', $data['status'] === Event::HELD ? 'Outcome saved. Attendees get their points.' : 'Marked as cancelled.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request->user(), $event);
        $event->delete();
        Audit::record('events.delete', "Deleted “{$event->title}”");

        return redirect()->route('events')->with('status', 'Event deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(config('structure.event_types')))],
            'lga_id' => ['required', 'integer'],
            'ward_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:160'],
            'expected' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $lga = Lga::query()->find($data['lga_id']);
        $ward = filled($data['ward_id'] ?? null) ? Ward::query()->where('lga_id', $data['lga_id'])->find($data['ward_id']) : null;

        if (! $lga || ($ward ? ! $user->canSeeWard($ward) : ! $user->atLeast(UserRole::LgaLeader) || ! $user->canSeeLga($lga))) {
            throw ValidationException::withMessages(['ward_id' => $user->atLeast(UserRole::LgaLeader) ? 'Choose an LGA or ward in your area.' : 'Choose your ward.']);
        }

        return [
            'title' => trim($data['title']),
            'type' => $data['type'],
            'lga_id' => $lga->id,
            'ward_id' => $ward?->id,
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], Time::zone())->utc(),
            'venue' => $data['venue'] ?? null,
            'expected' => $data['expected'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function authorizeEvent(User $user, Event $event): void
    {
        abort_unless(Event::query()->visibleTo($user)->whereKey($event->id)->exists(), 403, 'This event is outside your area.');
    }

    /**
     * @return array{lgaOptions: array<int, string>, wardOptions: list<array{id: int, lga_id: int, name: string}>}
     */
    private function formOptions(User $user): array
    {
        $wards = Ward::query()->visibleTo($user)->orderBy('name')->get(['id', 'lga_id', 'name']);

        return [
            'lgaOptions' => Lga::query()->whereIn('id', $wards->pluck('lga_id')->unique())->orderBy('name')->pluck('name', 'id')->all(),
            'wardOptions' => $wards->map(fn (Ward $ward) => ['id' => $ward->id, 'lga_id' => $ward->lga_id, 'name' => $ward->name])->values()->all(),
        ];
    }
}
