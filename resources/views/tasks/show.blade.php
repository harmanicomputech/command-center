<x-layouts.app :title="$task->title">
    <x-page-header :title="$task->title" :eyebrow="$task->typeLabel()" :back="route('tasks')"
        description="{{ $task->assignee?->name ?? 'Everyone in the ward' }} · {{ $task->ward->fullName() }}{{ $task->due_on ? ' · due '.$task->due_on->format('D j M') : '' }}">
        <x-slot:actions>
            <form method="post" action="{{ route('tasks.status', $task) }}">
                @csrf
                <input type="hidden" name="status" value="{{ $task->status === 'open' ? 'closed' : 'open' }}">
                <x-button variant="secondary" :icon="$task->status === 'open' ? 'circle-check' : 'rotate-ccw'">{{ $task->status === 'open' ? 'Close task' : 'Reopen' }}</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($task->description)
                <x-card title="Instructions" icon="file-text"><p class="text-sm whitespace-pre-line text-muted">{{ $task->description }}</p></x-card>
            @endif
            <x-card title="Updates from the field" icon="activity">
                @forelse ($task->reports->sortByDesc('reported_at') as $report)
                    <div class="flex gap-3 border-b border-line py-4 first:pt-0 last:border-0 last:pb-0">
                        <x-avatar :name="$report->user->name" :size="34" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm"><span class="font-semibold">{{ $report->user->name }}</span> <span class="text-subtle">· {{ \App\Support\Time::local($report->reported_at, 'D j M, g:i A') }}</span></p>
                            <p class="mt-0.5 text-sm text-muted">
                                @if ($report->done)<x-badge tone="good" class="mr-1">Done</x-badge>@endif
                                {{ $report->count ? '+'.number_format($report->count).' '.($task->target_unit ?? '') : '' }}{{ $report->note ? ($report->count ? ' · ' : '').$report->note : '' }}
                            </p>
                            @if ($report->photos->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($report->photos as $photo)
                                        <a href="{{ $photo->url() }}" target="_blank"><img src="{{ $photo->url(true) }}" alt="Proof photo from {{ $report->user->name }}" class="size-24 rounded-xl border border-line object-cover" loading="lazy"></a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-empty icon="inbox" title="No updates yet" description="Updates, counts and photos appear here as agents send them." compact />
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Progress" icon="target">
                @foreach ($people as $person)
                    @php
                        $count = (int) $task->reports->where('user_id', $person->id)->sum('count');
                        $done = $task->reports->where('user_id', $person->id)->contains('done', true);
                    @endphp
                    <div class="border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                        <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
                            <span class="truncate font-medium">{{ $person->name }}</span>
                            @if ($done)<x-badge tone="good">Done</x-badge>@else<span class="num text-muted">{{ number_format($count) }}{{ $task->target ? ' / '.number_format($task->target) : '' }}</span>@endif
                        </div>
                        @if ($task->target)<x-progress :value="$done ? $task->target : $count" :max="$task->target" />@endif
                    </div>
                @endforeach
            </x-card>
            <form method="post" action="{{ route('tasks.destroy', $task) }}" x-data x-on:submit="if (! confirm('Delete this task and its updates?')) $event.preventDefault()">
                @csrf @method('delete')
                <x-button variant="ghost" icon="trash-2" class="w-full">Delete task</x-button>
            </form>
        </div>
    </div>
</x-layouts.app>
