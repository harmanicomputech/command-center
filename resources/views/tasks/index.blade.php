<x-layouts.app title="Tasks">
    <x-page-header title="Tasks" eyebrow="Field" description="Work for agents: door-to-door, meetings, market storms, flyers and follow-ups. Agents see their tasks offline and send progress with a photo or a count.">
        <x-slot:actions>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'task')">New task</x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $tabs = collect(\App\Http\Controllers\Console\TaskController::FILTERS)->map(fn ($label, $key) => [$label, route('tasks', ['filter' => $key]), $filter === $key, number_format($counts[$key])])->values()->all();
    @endphp
    <x-tabs :items="$tabs" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="card divide-y divide-line overflow-hidden">
            @forelse ($tasks as $task)
                @php
                    $doneCount = $task->reports->where('done', true)->pluck('user_id')->unique()->count();
                    $progress = (int) $task->reports->sum('count');
                @endphp
                <a href="{{ route('tasks.show', $task) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4 transition-colors hover:bg-brand-softer sm:px-5">
                    <span class="grid size-10 flex-none place-items-center rounded-xl {{ $task->status === 'closed' ? 'bg-good-soft text-good' : ($task->isOverdue() ? 'bg-bad-soft text-bad' : 'bg-brand-soft text-brand-fg') }}"><x-icon :name="$task->status === 'closed' ? 'circle-check' : 'list-todo'" size="19" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $task->title }}</p>
                        <p class="truncate text-sm text-muted">{{ $task->typeLabel() }} · {{ $task->assignee?->name ?? 'Whole ward' }} · {{ $task->ward->fullName() }}</p>
                    </div>
                    <div class="flex w-full flex-wrap items-center gap-2 pl-14 sm:w-auto sm:pl-0">
                        @if ($task->target)<span class="num text-sm text-muted">{{ number_format($progress) }} / {{ $task->targetLabel() }}</span>@endif
                        @if ($doneCount)<x-badge tone="good">{{ $task->assignee_id ? 'Done' : $doneCount.' done' }}</x-badge>@endif
                        @if ($task->isOverdue())
                            <x-badge tone="bad" dot>Overdue {{ $task->due_on->format('j M') }}</x-badge>
                        @elseif ($task->due_on && $task->status === 'open')
                            <x-badge dot>Due {{ $task->due_on->format('j M') }}</x-badge>
                        @endif
                    </div>
                </a>
            @empty
                <x-empty icon="list-todo" title="No {{ $filter === 'all' ? '' : strtolower(\App\Http\Controllers\Console\TaskController::FILTERS[$filter]) }} tasks" description="Set a task for an agent or a whole ward. It reaches their phone next time it syncs.">
                    <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'task')">New task</x-button>
                </x-empty>
            @endforelse
        </div>

        <x-card title="By ward" description="Tasks done and overdue." icon="map-pin">
            @forelse ($byWard->take(12) as $row)
                <div class="border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
                        <span class="truncate font-medium">{{ $row['ward']->name }}</span>
                        <span class="num flex-none text-muted">{{ $row['done'] }}/{{ $row['total'] }}@if ($row['overdue']) · <span class="font-semibold text-bad">{{ $row['overdue'] }} late</span>@endif</span>
                    </div>
                    <x-progress :value="$row['done']" :max="$row['total']" :tone="$row['overdue'] ? 'warn' : 'brand'" />
                </div>
            @empty
                <p class="text-sm text-muted">No tasks yet.</p>
            @endforelse
        </x-card>
    </div>
    @if ($tasks->hasPages())<div class="mt-4">{{ $tasks->links() }}</div>@endif

    <x-modal name="task" title="New task" width="max-w-xl">
        <form method="post" action="{{ route('tasks.store') }}" class="space-y-5" x-data="{ ward: @js((string) old('ward_id', auth()->user()->ward_id ?? array_key_first($wardOptions))), agents: @js($agentOptions) }">
            @csrf
            <x-input name="title" label="Title" placeholder="e.g. Door-to-door on Market Road" required />
            <x-select name="type" label="Type" :options="config('field.task_types')" required />
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="t-ward">Ward</label>
                    <select id="t-ward" name="ward_id" class="input" x-model="ward">
                        @foreach ($wardOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="t-assignee">For</label>
                    <select id="t-assignee" name="assignee_id" class="input">
                        <option value="">Everyone in the ward</option>
                        <template x-for="agent in agents.filter(a => String(a.ward_id) === ward)" :key="agent.id">
                            <option :value="agent.id" x-text="agent.name"></option>
                        </template>
                    </select>
                    @error('assignee_id')<p class="field-error"><x-icon name="circle-alert" />{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3">
                <x-input name="due_on" type="date" label="Due" optional />
                <x-input name="target" type="number" min="1" label="Target" optional />
                <x-select name="target_unit" label="Of" :options="config('field.target_units')" class="col-span-2 sm:col-span-1" />
            </div>
            <x-segmented name="proof" label="Proof when done" :options="config('field.proofs')" value="photo" :cols="3" />
            <x-textarea name="description" label="Instructions" optional rows="3" />
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="send">Set task</x-button>
            </div>
        </form>
    </x-modal>
    @if ($errors->any())
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'task'))"></div>
    @endif
</x-layouts.app>
