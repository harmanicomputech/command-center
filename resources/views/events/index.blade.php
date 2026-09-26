@php
    $zone = \App\Support\Time::zone();
    $today = \App\Support\Time::now()->format('Y-m-d');
@endphp
<x-layouts.app title="Events" wide>
    <x-page-header title="Events" eyebrow="Field" description="Meetings, rallies, town halls and market storms across your area. After an event, record who came: attendees earn points and it keeps the ward off the red list.">
        <x-slot:actions>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'event')">Plan an event</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($toRecord->isNotEmpty())
        <x-alert tone="warn" title="{{ $toRecord->count() }} {{ $toRecord->count() === 1 ? 'event needs' : 'events need' }} an outcome" class="mb-6">
            <ul class="mt-1 space-y-1">
                @foreach ($toRecord as $event)
                    <li><a href="{{ route('events.show', $event) }}" class="link">{{ $event->title }}</a> · {{ \App\Support\Time::local($event->starts_at, 'D j M') }} · {{ $event->place() }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        {{-- Month calendar (tablet and up) --}}
        <section class="card hidden overflow-hidden md:block">
            <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold">{{ $month->format('F Y') }}</h2>
                <div class="flex gap-1">
                    <x-button :href="route('events', ['month' => $month->copy()->subMonth()->format('Y-m')])" variant="ghost" size="sm" square icon="chevron-left" aria-label="Previous month" />
                    <x-button :href="route('events')" variant="secondary" size="sm">Today</x-button>
                    <x-button :href="route('events', ['month' => $month->copy()->addMonth()->format('Y-m')])" variant="ghost" size="sm" square icon="chevron-right" aria-label="Next month" />
                </div>
            </header>
            <div class="grid grid-cols-7 border-b border-line bg-surface-2 text-center text-xs font-semibold text-subtle">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)<div class="py-2">{{ $day }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7">
                @for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay())
                    @php
                        $key = $day->format('Y-m-d');
                        $events = $byDay[$key] ?? collect();
                    @endphp
                    <div class="min-h-28 border-r border-b border-line p-1.5 [&:nth-child(7n)]:border-r-0 {{ $day->month !== $month->month ? 'bg-surface-2/60' : '' }}">
                        <p class="mb-1 flex justify-end">
                            <span class="num grid size-6 place-items-center rounded-full text-xs font-semibold {{ $key === $today ? 'bg-brand text-on-brand' : ($day->month !== $month->month ? 'text-subtle' : 'text-muted') }}">{{ $day->day }}</span>
                        </p>
                        <div class="space-y-1">
                            @foreach ($events->take(3) as $event)
                                <a href="{{ route('events.show', $event) }}" title="{{ $event->title }} · {{ $event->place() }}"
                                    class="block truncate rounded-md px-1.5 py-1 text-[11px] leading-tight font-semibold {{ $event->status === 'cancelled' ? 'bg-surface-2 text-subtle line-through' : ($event->status === 'held' ? 'bg-good-soft text-good' : 'bg-brand-soft text-brand-fg') }}">
                                    <span class="num hidden font-medium opacity-80 2xl:inline">{{ \App\Support\Time::local($event->starts_at, 'H:i') }}</span> {{ $event->title }}
                                </a>
                            @endforeach
                            @if ($events->count() > 3)<p class="px-1.5 text-[11px] font-medium text-subtle">+{{ $events->count() - 3 }} more</p>@endif
                        </div>
                    </div>
                @endfor
            </div>
        </section>

        {{-- Agenda --}}
        <section>
            <h2 class="mb-3 text-base font-semibold">Coming up</h2>
            <div class="card divide-y divide-line">
                @forelse ($upcoming as $event)
                    <a href="{{ route('events.show', $event) }}" class="flex gap-4 p-4 transition-colors hover:bg-brand-softer">
                        <div class="w-12 flex-none rounded-xl border border-line bg-surface-2 py-1.5 text-center">
                            <p class="text-[10px] font-bold tracking-wide text-bad uppercase">{{ \App\Support\Time::local($event->starts_at, 'M') }}</p>
                            <p class="num text-lg leading-tight font-bold">{{ \App\Support\Time::local($event->starts_at, 'j') }}</p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $event->title }}</p>
                            <p class="truncate text-sm text-muted">{{ $event->typeLabel() }} · {{ \App\Support\Time::local($event->starts_at, 'D, g:i A') }}</p>
                            <p class="truncate text-xs text-subtle">{{ $event->place() }}{{ $event->venue ? ' · '.$event->venue : '' }}</p>
                        </div>
                    </a>
                @empty
                    <x-empty icon="calendar" title="Nothing planned" description="Plan the next ward meeting or market storm." compact>
                        <x-button type="button" size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'event')">Plan an event</x-button>
                    </x-empty>
                @endforelse
            </div>
        </section>
    </div>

    <x-modal name="event" title="Plan an event" width="max-w-xl">
        <form method="post" action="{{ route('events.store') }}" class="space-y-6">
            @csrf
            @include('events._form')
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="check">Plan event</x-button>
            </div>
        </form>
    </x-modal>
    @if ($errors->any())
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'event'))"></div>
    @endif
</x-layouts.app>
