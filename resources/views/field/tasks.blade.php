<x-layouts.field title="My tasks">
    <h1 class="text-2xl font-bold tracking-tight">My tasks</h1>
    <p class="mt-1 text-sm text-muted">From your coordinator. Update them even without network.</p>

    <div class="mt-5 space-y-3">
        @forelse ($tasks as $task)
            @php
                $progress = $task->progressFor($user);
                $done = $task->doneBy($user) || $task->status === 'closed';
            @endphp
            <a href="{{ route('field.task', $task) }}" class="card card-interactive block p-4 {{ $done ? 'opacity-70' : '' }}">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 grid size-10 flex-none place-items-center rounded-xl {{ $done ? 'bg-good-soft text-good' : ($task->isOverdue() ? 'bg-bad-soft text-bad' : 'bg-brand-soft text-brand-fg') }}">
                        <x-icon :name="$done ? 'circle-check' : 'list-todo'" size="20" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $task->title }}</p>
                        <p class="text-sm text-muted">{{ $task->typeLabel() }}{{ $task->assignee_id ? '' : ' · whole ward' }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @if ($done)
                                <x-badge tone="good">Done</x-badge>
                            @elseif ($task->isOverdue())
                                <x-badge tone="bad" dot>Overdue {{ $task->due_on->format('j M') }}</x-badge>
                            @elseif ($task->due_on)
                                <x-badge dot>Due {{ $task->due_on->format('D j M') }}</x-badge>
                            @endif
                            @if ($task->target)<x-badge>{{ number_format($progress) }} / {{ $task->targetLabel() }}</x-badge>@endif
                            @if ($task->proof === 'photo' && ! $done)<x-badge icon="upload">Photo needed</x-badge>@endif
                        </div>
                        @if ($task->target && ! $done)<x-progress :value="$progress" :max="$task->target" class="mt-3" />@endif
                    </div>
                    <x-icon name="chevron-right" class="mt-2 flex-none text-subtle" />
                </div>
            </a>
        @empty
            <div class="card"><x-empty icon="list-todo" title="No tasks right now" description="When your coordinator gives you a task, it appears here. Meanwhile, keep registering voters." >
                <x-button :href="route('field.register')" icon="plus">Register a voter</x-button>
            </x-empty></div>
        @endforelse
    </div>
</x-layouts.field>
