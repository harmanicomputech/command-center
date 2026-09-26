<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Ward;
use App\Services\PushAlerts;
use App\Support\Audit;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tasks for agents (one person or a whole ward): door-to-door, meetings,
 * market storms, flyers, follow-ups. Coordinators set them; leaders see
 * completion per ward and what is overdue.
 */
class TaskController extends Controller
{
    public const FILTERS = ['open' => 'Open', 'overdue' => 'Overdue', 'closed' => 'Closed', 'all' => 'All'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS) ? (string) $request->query('filter') : 'open';
        $today = Time::now()->format('Y-m-d');
        $base = Task::query()->inAreaOf($user);

        $tasks = (clone $base)->with('ward.lga', 'assignee', 'reports')
            ->when($filter === 'open', fn ($query) => $query->where('status', Task::OPEN))
            ->when($filter === 'overdue', fn ($query) => $query->where('status', Task::OPEN)->whereNotNull('due_on')->where('due_on', '<', $today))
            ->when($filter === 'closed', fn ($query) => $query->where('status', Task::CLOSED))
            ->orderByRaw('due_on is null')->orderBy('due_on')->latest('id')
            ->paginate(30)->withQueryString();

        // Completion per ward: tasks where every assignee (or someone in the ward) is done.
        $byWard = (clone $base)->with('ward', 'reports')->get()->groupBy('ward_id')->map(fn ($wardTasks) => [
            'ward' => $wardTasks->first()->ward,
            'total' => $wardTasks->count(),
            'done' => $wardTasks->filter(fn (Task $task) => $task->status === Task::CLOSED || $task->reports->contains('done', true))->count(),
            'overdue' => $wardTasks->filter->isOverdue()->count(),
        ])->sortByDesc('overdue')->values();

        return view('tasks.index', [
            'tasks' => $tasks,
            'filter' => $filter,
            'counts' => [
                'open' => (clone $base)->where('status', Task::OPEN)->count(),
                'overdue' => (clone $base)->where('status', Task::OPEN)->whereNotNull('due_on')->where('due_on', '<', $today)->count(),
                'closed' => (clone $base)->where('status', Task::CLOSED)->count(),
                'all' => (clone $base)->count(),
            ],
            'byWard' => $byWard,
            ...$this->formOptions($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(config('field.task_types')))],
            'description' => ['nullable', 'string', 'max:2000'],
            'ward_id' => ['required', 'integer'],
            'assignee_id' => ['nullable', 'integer'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'target' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'target_unit' => ['nullable', Rule::in(array_keys(config('field.target_units')))],
            'proof' => ['required', Rule::in(array_keys(config('field.proofs')))],
        ]);

        $ward = Ward::query()->visibleTo($user)->find($data['ward_id']);
        if (! $ward) {
            throw ValidationException::withMessages(['ward_id' => 'Choose a ward in your area.']);
        }

        $assignee = filled($data['assignee_id'] ?? null)
            ? User::query()->where('ward_id', $ward->id)->where('role', UserRole::Agent)->find($data['assignee_id'])
            : null;
        if (filled($data['assignee_id'] ?? null) && ! $assignee) {
            throw ValidationException::withMessages(['assignee_id' => 'Choose an agent in that ward, or the whole ward.']);
        }

        $task = Task::create([...$data, 'lga_id' => $ward->lga_id, 'assignee_id' => $assignee?->id, 'created_by' => $user->id, 'status' => Task::OPEN]);
        PushAlerts::queue('task_assigned', ['title' => 'New task: '.$task->title, 'body' => ($task->due_on ? 'Due '.$task->due_on->format('D j M').'. ' : '').'Open My tasks to start.', 'url' => route('field.task', $task, false), 'tag' => 'task-'.$task->id],
            userIds: $assignee ? [$assignee->id] : User::query()->where('ward_id', $ward->id)->where('role', UserRole::Agent)->pluck('id')->all());
        Audit::record('tasks.create', "Set the task “{$task->title}” for ".($assignee?->name ?? 'everyone in '.$ward->name), ['task_id' => $task->id]);

        return redirect()->route('tasks.show', $task)->with('status', 'Task set. It shows on '.($assignee ? $assignee->firstName().'’s' : 'the ward’s agents’').' phones next time they sync.');
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorizeTask($request->user(), $task);
        $task->load('ward.lga', 'assignee', 'author', 'reports.user', 'reports.photos');

        $people = $task->assignee_id
            ? collect([$task->assignee])
            : User::query()->where('ward_id', $task->ward_id)->where('role', UserRole::Agent)->whereNull('disabled_at')->orderBy('name')->get();

        return view('tasks.show', ['task' => $task, 'people' => $people]);
    }

    public function status(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($request->user(), $task);
        $status = $request->validate(['status' => ['required', Rule::in([Task::OPEN, Task::CLOSED])]])['status'];
        $task->update(['status' => $status]);

        return back()->with('status', $status === Task::CLOSED ? 'Task closed.' : 'Task reopened.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($request->user(), $task);
        $task->delete();
        Audit::record('tasks.delete', "Deleted the task “{$task->title}”");

        return redirect()->route('tasks')->with('status', 'Task deleted.');
    }

    private function authorizeTask(User $user, Task $task): void
    {
        abort_unless($user->canSeeWard($task->ward), 403, 'This task is outside your area.');
    }

    /**
     * @return array{wardOptions: array<int, string>, agentOptions: list<array{id: int, ward_id: int, name: string}>}
     */
    private function formOptions(User $user): array
    {
        $wards = Ward::query()->visibleTo($user)->with('lga')->orderBy('name')->get();

        return [
            'wardOptions' => $wards->mapWithKeys(fn (Ward $ward) => [$ward->id => $user->role === UserRole::WardCoordinator ? $ward->name : $ward->fullName()])->all(),
            'agentOptions' => User::query()->whereIn('ward_id', $wards->pluck('id'))->where('role', UserRole::Agent)->whereNull('disabled_at')->orderBy('name')
                ->get(['id', 'ward_id', 'name'])->map(fn (User $agent) => ['id' => $agent->id, 'ward_id' => $agent->ward_id, 'name' => $agent->name])->all(),
        ];
    }
}
