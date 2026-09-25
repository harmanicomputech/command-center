<x-layouts.field title="Home">
    <x-slot:pill>
        <span class="badge badge-good"><x-icon name="cloud-check" />All synced</span>
    </x-slot:pill>

    <section class="rise">
        <p class="text-sm font-medium text-muted">{{ \App\Support\Time::now()->format('l j F') }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $greeting }}, {{ $user->firstName() }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $user->areaLabel() }}</p>
    </section>

    <section class="card rise mt-5 overflow-hidden p-5" style="--i: 1">
        <div class="flex items-center gap-5">
            <x-progress-ring :value="$today" :max="$target" :size="112" :stroke="11" label="Registrations today">
                <span class="num text-2xl leading-none font-bold">{{ $today }}</span>
                <span class="mt-1 text-xs font-medium text-subtle">of {{ $target }}</span>
            </x-progress-ring>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">Today’s registrations</p>
                <p class="mt-1 text-sm text-muted">{{ $today >= $target ? 'Target reached. Brilliant work.' : ($target - $today).' to go to reach today’s target.' }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="badge badge-accent"><span aria-hidden="true">🔥</span> {{ $streak }}-day streak</span>
                    <span class="badge">{{ $rank ? '#'.$rank.' in your ward' : 'Not ranked yet' }}</span>
                </div>
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
</x-layouts.field>
