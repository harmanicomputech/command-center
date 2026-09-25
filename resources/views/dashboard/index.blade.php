<x-layouts.app title="Dashboard">
    <x-page-header :title="$greeting.', '.auth()->user()->firstName()" :eyebrow="\App\Support\Time::now()->format('l j F')"
        description="The daily dashboard fills in as the field starts registering voters. Here is the campaign structure today.">
        <x-slot:actions>
            <x-badge tone="accent" icon="calendar">{{ max(0, $daysToGo) }} days to election day</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi class="rise" style="--i: 0" label="Registered voters" :value="$registered" icon="vote" :hint="'In '.$wardCount.' wards (register)'" />
        <x-kpi class="rise" style="--i: 1" label="Voters canvassed" :value="0" icon="user-round-check" :hint="'Target '.number_format($target)" />
        <x-kpi class="rise" style="--i: 2" label="Field agents" :value="$agents" icon="users" :hint="$activeAgents.' active in 14 days'" />
        <x-kpi class="rise" style="--i: 3" label="Coordinated wards" :value="$coordinatorCoverage" suffix="%" icon="shield-check" :hint="$wardsWithCoordinator.' of '.$wardCount.' wards have a coordinator'" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" title="Field activity" description="Registrations, tasks and issues from the Field Force app." icon="activity">
            <x-empty icon="smartphone" title="No field activity yet" description="When agents start registering voters on their phones, today’s numbers, the week’s trend and the map appear here.">
                @if (Route::has('users'))
                    <x-button :href="route('users')" icon="user-plus" variant="secondary">Invite your team</x-button>
                @endif
            </x-empty>
        </x-card>

        @if ($setup)
            <x-card title="Set-up checklist" description="{{ collect($setup)->where('done', true)->count() }} of {{ count($setup) }} done" icon="list-todo">
                <x-progress :value="collect($setup)->where('done', true)->count()" :max="count($setup)" label="Set-up progress" class="mb-4" />
                <ul class="-mx-2 space-y-0.5">
                    @foreach ($setup as $step)
                        <li>
                            <a href="{{ $step['url'] }}" class="flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-surface-2">
                                @if ($step['done'])
                                    <span class="grid size-6 flex-none place-items-center rounded-full bg-good-soft text-good"><x-icon name="check" size="14" /></span>
                                    <span class="flex-1 text-muted line-through decoration-line-strong">{{ $step['label'] }}</span>
                                @else
                                    <span class="size-6 flex-none rounded-full border-[1.5px] border-dashed border-line-strong"></span>
                                    <span class="flex-1 font-medium">{{ $step['label'] }}</span>
                                    <x-icon name="chevron-right" size="16" class="text-subtle" />
                                @endif
                                <span class="sr-only">{{ $step['done'] ? '(done)' : '(to do)' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @else
            <x-card title="Your area" icon="map-pin">
                <p class="text-sm text-muted">{{ auth()->user()->areaLabel() }}</p>
                <x-button :href="route('areas')" variant="secondary" size="sm" class="mt-4" icon-right="arrow-right">See the wards</x-button>
            </x-card>
        @endif
    </div>
</x-layouts.app>
