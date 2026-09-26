<x-layouts.field title="Home">
    <section class="rise">
        <p class="text-sm font-medium text-muted">{{ \App\Support\Time::now()->format('l j F') }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $greeting }}, {{ $user->firstName() }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $user->areaLabel() }}</p>
    </section>

    <x-alert tone="bad" class="rise mt-4" title="Sign in again to send your work" x-data x-show="$store.outbox.needsSignIn && $store.outbox.pending" x-cloak>
        Your registrations are safe on this phone. <a href="{{ route('login') }}" class="link">Sign in</a> and they send straight away.
    </x-alert>

    <section class="card rise mt-5 overflow-hidden p-5" style="--i: 1">
        <div class="flex items-center gap-5">
            <x-progress-ring :value="$stats['today']" :max="$target" :size="112" :stroke="11" label="Registrations today">
                <span class="num text-3xl leading-none font-bold">{{ $stats['today'] }}</span>
                <span class="mt-1 text-xs font-medium text-subtle">of {{ $target }} today</span>
            </x-progress-ring>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">{{ $stats['today'] >= $target ? 'Target reached. Brilliant work.' : ($target - $stats['today']).' to go today' }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="badge badge-accent"><span aria-hidden="true">🔥</span> {{ $stats['streak'] }}-day streak</span>
                    <a href="{{ route('field.leaderboard') }}" class="badge">{{ $stats['rank'] ? '#'.$stats['rank'].' of '.$stats['ranked'].' in your ward' : 'Not ranked yet this week' }}</a>
                </div>
                <p class="mt-3 text-xs text-muted"><span class="num font-semibold text-ink">{{ number_format($stats['week']) }}</span> this week · <span class="num font-semibold text-ink">{{ number_format($stats['total']) }}</span> in all</p>
            </div>
        </div>
        <x-button :href="route('field.register')" size="xl" icon="plus" class="mt-5 w-full">Register a voter</x-button>
    </section>

    <section class="rise mt-6" style="--i: 2">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-semibold">My open tasks</h2>
            <a href="{{ route('field.tasks') }}" class="link text-sm">See all</a>
        </div>
        <div class="card divide-y divide-line">
            @forelse ($tasks as $task)
                <a href="{{ route('field.task', $task) }}" class="flex items-center gap-3 p-4">
                    <span class="grid size-10 flex-none place-items-center rounded-xl {{ $task->isOverdue() ? 'bg-bad-soft text-bad' : 'bg-brand-soft text-brand-fg' }}"><x-icon name="list-todo" size="19" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ $task->title }}</span>
                        <span class="block text-xs text-subtle">{{ $task->due_on ? ($task->isOverdue() ? 'Overdue since ' : 'Due ').$task->due_on->format('D j M') : $task->typeLabel() }}</span>
                    </span>
                    <x-icon name="chevron-right" size="16" class="text-subtle" />
                </a>
            @empty
                <x-empty icon="list-todo" title="No open tasks" description="When your coordinator gives you a task, it shows here, even without network." compact />
            @endforelse
        </div>
    </section>

    @if ($surveys->isNotEmpty())
        <a href="{{ route('field.surveys') }}" class="card card-interactive rise mt-6 flex items-center gap-4 p-4" style="--i: 3">
            <span class="grid size-11 flex-none place-items-center rounded-xl bg-info-soft text-info"><x-icon name="clipboard-list" /></span>
            <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $surveys->count() }} {{ $surveys->count() === 1 ? 'survey' : 'surveys' }} running in your ward</span><span class="block text-sm text-muted">+{{ \App\Support\Settings::int('points.survey') }} points for each person you ask</span></span>
            <x-icon name="chevron-right" class="text-subtle" />
        </a>
    @endif

    <a href="{{ route('field.narratives') }}" class="card card-interactive rise mt-6 flex items-center gap-4 p-4" style="--i: 3">
        <span class="grid size-11 flex-none place-items-center rounded-xl bg-accent-soft text-accent-fg"><x-icon name="radio" /></span>
        <span class="min-w-0 flex-1"><span class="block font-semibold">Heard something?</span><span class="block text-sm text-muted">Report a rumour or what people are saying</span></span>
        <x-icon name="chevron-right" class="text-subtle" />
    </a>

    <section class="rise mt-6" style="--i: 3">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-semibold">Recently registered</h2>
            <a href="{{ route('field.registrations') }}" class="link text-sm">See all</a>
        </div>
        <div class="card divide-y divide-line">
            @forelse ($recent as $voter)
                <div class="flex items-center gap-3 p-4">
                    <x-avatar :name="$voter->name ?? '?'" :size="36" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $voter->name ?? 'Erased' }}</p>
                        <p class="text-xs text-subtle">{{ $voter->captured_at->diffForHumans() }} · {{ $voter->supportLabel() }}</p>
                    </div>
                    @include('field._status', ['voter' => $voter])
                </div>
            @empty
                <x-empty icon="user-plus" title="No one yet" description="Your first registration shows here." compact />
            @endforelse
        </div>
    </section>
</x-layouts.field>
