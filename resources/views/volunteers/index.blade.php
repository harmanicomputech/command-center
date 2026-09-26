@php
    $tabs = [['To follow up', route('volunteers'), ! $status, ($counts['new'] ?? 0) + ($counts['contacted'] ?? 0)]];
    foreach (config('volunteers.statuses') as $key => $option) {
        $tabs[] = [$option['label'], route('volunteers', ['status' => $key]), $status === $key, $counts[$key] ?? 0];
    }
@endphp
<x-layouts.app title="Volunteers">
    <x-page-header title="Volunteers" eyebrow="Field" description="People who offered to help, from the campaign website and the join page. Call them, then make the willing ones field agents in one step.">
        <x-slot:actions>
            <x-button :href="route('join')" variant="secondary" icon="external-link" target="_blank">Open the join page</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi label="New this week" :value="$week" icon="user-plus" />
        <x-kpi label="To call" :value="$counts['new'] ?? 0" icon="phone" :href="route('volunteers', ['status' => 'new'])" />
        <x-kpi label="Joined the team" :value="$counts['joined'] ?? 0" icon="user-round-check" :href="route('volunteers', ['status' => 'joined'])" />
    </div>

    <x-tabs :items="$tabs" />

    <div class="grid gap-3">
        @forelse ($volunteers as $volunteer)
            <article class="card flex flex-col gap-4 p-5 lg:flex-row lg:items-center">
                <x-avatar :name="$volunteer->name" :size="44" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold">{{ $volunteer->name }}</p>
                        <x-badge :tone="$volunteer->statusTone()" dot>{{ $volunteer->statusLabel() }}</x-badge>
                    </div>
                    <p class="mt-0.5 text-sm text-muted"><a href="tel:{{ $volunteer->phone }}" class="num font-medium text-brand-fg">{{ \App\Support\Phone::local($volunteer->phone) }}</a> · {{ $volunteer->ward?->name ?? 'Ward not given' }}, {{ $volunteer->lga?->name }} · {{ $volunteer->created_at->diffForHumans() }} · {{ $volunteer->source }}</p>
                    @if ($volunteer->helpLabels())
                        <div class="mt-2 flex flex-wrap gap-1.5">@foreach ($volunteer->helpLabels() as $helpLabel)<x-badge>{{ $helpLabel }}</x-badge>@endforeach</div>
                    @endif
                    @if ($volunteer->message)<p class="mt-2 text-sm">“{{ $volunteer->message }}”</p>@endif
                    @if ($volunteer->user)<p class="mt-2 text-sm text-good">On the team as {{ $volunteer->user->name }}</p>@endif
                </div>
                @if ($volunteer->status !== 'joined')
                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        @if ($canInvite)
                            <form method="post" action="{{ route('volunteers.invite', $volunteer) }}" class="flex gap-2">
                                @csrf
                                <label class="sr-only" for="vw-{{ $volunteer->id }}">Ward</label>
                                <select id="vw-{{ $volunteer->id }}" name="ward_id" class="input h-9 w-44 py-0 text-sm">
                                    @foreach ($wards->where('lga_id', $volunteer->lga_id) as $ward)<option value="{{ $ward->id }}" @selected($volunteer->ward_id === $ward->id)>{{ $ward->name }}</option>@endforeach
                                </select>
                                <x-button size="sm" icon="user-plus">Make agent</x-button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('volunteers.status', $volunteer) }}">
                            @csrf
                            @if ($volunteer->status === 'new')
                                <input type="hidden" name="status" value="contacted"><x-button size="sm" variant="secondary" icon="phone">Called</x-button>
                            @else
                                <input type="hidden" name="status" value="not_now"><x-button size="sm" variant="ghost" icon="x">Not now</x-button>
                            @endif
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="card"><x-empty icon="user-plus" title="No volunteers here" description="Sign-ups from the campaign website and the join page appear here." /></div>
        @endforelse
    </div>
    @if ($volunteers->hasPages())<div class="mt-4">{{ $volunteers->links() }}</div>@endif
</x-layouts.app>
