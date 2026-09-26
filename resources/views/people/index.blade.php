<x-layouts.app title="People">
    <x-page-header title="People" eyebrow="Field" description="The campaign structure in your area: LGA leaders, ward coordinators and agents. Engagement is from the last 14 days: active people did something, occasional ones only signed in.">
        <x-slot:actions>
            @if (Route::has('team') && auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::LgaLeader, \App\Enums\UserRole::WardCoordinator))
                <x-button :href="route('team')" icon="user-plus">Invite</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-3 gap-3 sm:gap-4">
        @foreach (\App\Services\Structure::LABELS as $key => [$label, $tone])
            <a href="{{ route('people', array_filter(['engagement' => $level === $key ? null : $key, 'role' => $role?->value, 'q' => $q ?: null])) }}"
                class="card card-interactive p-4 sm:p-5 {{ $level === $key ? 'ring-2 ring-brand' : '' }}">
                <p class="flex items-center gap-2 text-xs font-medium text-muted sm:text-sm"><span class="size-2 rounded-full {{ ['good' => 'bg-good', 'warn' => 'bg-warn'][$tone] ?? 'bg-line-strong' }}"></span>{{ $label }}</p>
                <p class="num mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ number_format($counts[$key] ?? 0) }}</p>
            </a>
        @endforeach
    </div>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="no-scrollbar -mx-4 flex gap-1.5 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <a href="{{ route('people', array_filter(['engagement' => $level, 'q' => $q ?: null])) }}" class="btn btn-sm {{ $role ? 'btn-ghost' : 'btn-secondary' }}">Everyone</a>
            @foreach ([\App\Enums\UserRole::LgaLeader, \App\Enums\UserRole::WardCoordinator, \App\Enums\UserRole::Agent] as $option)
                <a href="{{ route('people', array_filter(['role' => $option->value, 'engagement' => $level, 'q' => $q ?: null])) }}" class="btn btn-sm {{ $role === $option ? 'btn-secondary' : 'btn-ghost' }}">{{ $option->label() }}s</a>
            @endforeach
        </div>
        <form method="get" action="{{ route('people') }}" class="w-full lg:w-72">
            @if ($role)<input type="hidden" name="role" value="{{ $role->value }}">@endif
            @if ($level)<input type="hidden" name="engagement" value="{{ $level }}">@endif
            <x-input name="q" :value="$q" icon="search" placeholder="Search by name" aria-label="Search people" />
        </form>
    </div>

    <div class="card divide-y divide-line overflow-hidden">
        @forelse ($people as $person)
            <a href="{{ route('people.show', $person) }}" class="flex items-center gap-3 p-4 transition-colors hover:bg-brand-softer sm:gap-4 sm:px-5">
                <x-avatar :name="$person->name" :size="40" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $person->name }}</p>
                    <p class="truncate text-sm text-muted">{{ $person->role->label() }} · {{ $person->areaLabel() }}</p>
                </div>
                <div class="hidden text-right text-sm text-muted sm:block">
                    <p><span class="num font-semibold text-ink">{{ number_format($registrations[$person->id] ?? 0) }}</span> registered</p>
                    <p class="text-xs text-subtle">Seen {{ $person->last_seen_at?->diffForHumans() ?? 'never' }}</p>
                </div>
                <x-engagement :level="$engagement[$person->id]" />
                <x-icon name="chevron-right" size="16" class="hidden flex-none text-subtle sm:block" />
            </a>
        @empty
            <x-empty icon="users" title="No one matches" description="Try another filter, or invite people to your team." />
        @endforelse
    </div>
</x-layouts.app>
