<x-layouts.app :title="$ward->name">
    <x-page-header :title="$ward->name" :eyebrow="$lga->name.' LGA'" :back="route('areas.lga', $lga)"
        description="{{ $ward->polling_units_count }} polling units · {{ number_format($ward->registered_voters) }} registered voters · {{ number_format($registrations) }} canvassed">
        <x-slot:actions>
            @if ($health && $health['red'])
                <x-badge tone="bad" dot>{{ implode(' · ', $health['reasons']) }}</x-badge>
            @elseif ($health)
                <x-badge tone="good" dot>Structure healthy</x-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <x-card title="Team" icon="users">
                @forelse ($team as $member)
                    <a href="{{ route('people.show', $member) }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 transition-colors hover:bg-surface-2">
                        <x-avatar :name="$member->name" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $member->name }}</p>
                            <p class="text-xs text-subtle">{{ $member->role->label() }}</p>
                        </div>
                        <x-engagement :level="$engagement[$member->id]" />
                    </a>
                @empty
                    <x-empty icon="user-plus" title="No one here yet" description="This ward has no coordinator or agents. It shows red on the dashboard until it does." compact />
                @endforelse
            </x-card>

            <x-card title="Events" icon="calendar">
                <x-slot:actions><x-button :href="route('events')" variant="ghost" size="sm">Calendar</x-button></x-slot:actions>
                @forelse ($events as $event)
                    <a href="{{ route('events.show', $event) }}" class="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2.5 text-sm hover:bg-surface-2">
                        <span class="min-w-0"><span class="block truncate font-semibold">{{ $event->title }}</span><span class="text-xs text-subtle">{{ \App\Support\Time::local($event->starts_at, 'D j M, g:i A') }}</span></span>
                        <x-badge :tone="['held' => 'good', 'cancelled' => 'bad'][$event->status] ?? 'info'">{{ ucfirst($event->status) }}</x-badge>
                    </a>
                @empty
                    <p class="text-sm text-muted">No events in the last 30 days or planned.</p>
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            @if ($intel)
                @php
                    $zoneInfo = \App\Services\Intelligence::ZONES[$intel['zone']];
                    $segmentTitles = ['support' => ['How they feel', config('canvass.support_levels')], 'age' => ['Age', config('canvass.age_bands')], 'occupation' => ['Occupation', config('canvass.occupations')]];
                @endphp
                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-5 p-5 sm:p-6" style="background: linear-gradient(90deg, color-mix(in oklab, {{ $zoneInfo['color'] }}, transparent 86%), transparent)">
                        <div>
                            <p class="text-sm font-medium text-muted">Zone</p>
                            <p class="mt-1 text-2xl font-bold tracking-tight">{{ $zoneInfo['label'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-muted">Our share</p>
                            <p class="num mt-1 text-2xl font-bold tracking-tight">{{ $intel['share'] !== null ? $intel['share'].'%' : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-muted">This week</p>
                            <p class="mt-2"><x-delta :value="$intel['trend']" suffix=" pts" empty="Not enough data" /></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-muted">Priority</p>
                            <p class="num mt-1 text-2xl font-bold tracking-tight">{{ number_format($intel['priority']) }}</p>
                        </div>
                        <p class="w-full text-sm text-muted">{{ $intel['basis'] }}.{{ $intel['tag'] ? ' LGA preset: '.$intel['tag'].'.' : '' }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6 border-t border-line p-5 sm:grid-cols-3 sm:p-6">
                        @foreach ($segmentTitles as $key => [$title, $options])
                            @php $total = max(1, $segments[$key]->sum()); @endphp
                            <div>
                                <p class="eyebrow mb-2">{{ $title }}</p>
                                @forelse ($segments[$key]->sortDesc()->take(5) as $option => $n)
                                    <div class="mb-2">
                                        <div class="mb-1 flex justify-between text-xs"><span>{{ is_array($options[$option] ?? null) ? $options[$option]['label'] : ($options[$option] ?? $option) }}</span><span class="num text-muted">{{ round(100 * $n / $total) }}%</span></div>
                                        <x-progress :value="$n" :max="$total" />
                                    </div>
                                @empty
                                    <p class="text-sm text-subtle">No canvassing yet.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                    @if ($issues->isNotEmpty())
                        <div class="border-t border-line p-5 sm:p-6">
                            <p class="eyebrow mb-2">Top issues reported</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($issues as $category => $n)
                                    <x-badge tone="brand">{{ config('field.issue_categories.'.$category) }} · {{ $n }}</x-badge>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            <x-card title="Influence" description="Who shapes opinion here, and where the relationship stands." icon="landmark">
                <x-slot:actions>
                    <x-button type="button" size="sm" variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'influencer')">Add</x-button>
                </x-slot:actions>
                @forelse ($influencers as $influencer)
                    <div class="flex items-start justify-between gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $influencer->name }}</p>
                            <p class="truncate text-xs text-subtle">{{ $influencer->kindLabel() }}{{ $influencer->contact_name ? ' · '.$influencer->contact_name : '' }}</p>
                        </div>
                        <x-badge :tone="$influencer->relationshipTone()" dot>{{ $influencer->relationshipLabel() }}</x-badge>
                    </div>
                @empty
                    <p class="text-sm text-muted">No notes yet: add the traditional ruler, key churches and market associations.</p>
                @endforelse
            </x-card>

            <x-table title="Polling units">
                <thead>
                    <tr><th>Code</th><th>Name</th><th class="n">Registered</th></tr>
                </thead>
                <tbody>
                    @foreach ($units as $unit)
                        <tr>
                            <td class="num whitespace-nowrap text-muted">{{ $unit->inecCode() }}</td>
                            <td class="font-medium">{{ $unit->name ?? '—' }}</td>
                            <td class="n">{{ $unit->registered_voters === null ? '—' : number_format($unit->registered_voters) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>
    </div>

    <x-modal name="influencer" title="Add an influence note">
        <form method="post" action="{{ route('influencers.store') }}" class="space-y-6">
            @csrf
            @include('influencers._form', ['wards' => $wardOptions, 'wardId' => $ward->id])
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="check">Add note</x-button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>
