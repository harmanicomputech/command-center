@php
    $kinds = ['burst' => ['Many in minutes', 'zap'], 'phones' => ['Consecutive phone numbers', 'phone'], 'all_strong' => ['All strong supporters', 'target']];
@endphp
<x-layouts.app title="Review patterns">
    <x-page-header title="Review patterns" eyebrow="Leaderboard · private" :back="route('leaderboard')"
        description="Patterns worth a spot-check call before points count fully. A flag is a prompt to check, not proof of cheating. Never shown to agents or on the public board." />

    <div class="card divide-y divide-line">
        @forelse ($flags as $flag)
            @php [$label, $icon] = $kinds[$flag['kind']]; @endphp
            <div class="flex flex-wrap items-center gap-4 p-4 sm:px-5">
                <span class="grid size-10 flex-none place-items-center rounded-xl bg-warn-soft text-warn"><x-icon :name="$icon" size="19" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $flag['user']->name }} <span class="font-normal text-subtle">· {{ $flag['user']->ward?->name }}</span></p>
                    <p class="text-sm text-muted">{{ $label }}: {{ $flag['detail'] }}</p>
                </div>
                <x-button :href="route('voters', ['filter' => 'unverified', 'q' => ''])" variant="secondary" size="sm" icon="phone">Spot-check</x-button>
            </div>
        @empty
            <x-empty icon="shield-check" title="Nothing unusual" description="No bursts of registrations, runs of consecutive numbers, or all-strong-supporter records in the last 30 days." />
        @endforelse
    </div>
</x-layouts.app>
