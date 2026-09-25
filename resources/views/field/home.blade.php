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
                    <span class="badge">{{ $stats['rank'] ? '#'.$stats['rank'].' of '.$stats['ranked'].' in your ward' : 'Not ranked yet this week' }}</span>
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
        <div class="card">
            <x-empty icon="list-todo" title="No open tasks" description="When your coordinator gives you a task, it shows here, even without network." compact />
        </div>
    </section>

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
