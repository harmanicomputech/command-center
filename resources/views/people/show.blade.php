<x-layouts.app :title="$person->name">
    <x-page-header :title="$person->name" :eyebrow="$person->role->label()" :back="route('people')" :description="$person->areaLabel()">
        <x-slot:actions>
            <x-engagement :level="$level" />
            @if ($person->phone)
                <x-button :href="'tel:'.$person->phone" variant="secondary" icon="phone">Call</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-kpi label="Registered, all time" :value="$stats['total']" icon="user-round-check" :spark="$daily" :hint="$stats['week'].' this week'" />
        <x-kpi label="Verified" :value="$stats['verified']" icon="badge-check" :hint="$stats['invalid'].' invalid'" />
        <x-kpi label="Events attended" :value="$stats['events']" icon="calendar" />
        <div class="card flex flex-col justify-between gap-3 p-5">
            <p class="text-sm font-medium text-muted">Last activity</p>
            <p class="text-lg font-semibold">{{ $lastOutput ? $lastOutput->diffForHumans() : 'None yet' }}</p>
            <p class="text-xs text-subtle">Signed in {{ $person->last_seen_at?->diffForHumans() ?? 'never' }}</p>
        </div>
    </div>

    <x-card class="mt-6" title="Details" icon="circle-user">
        <dl class="grid grid-cols-1 gap-x-8 text-sm sm:grid-cols-2">
            @foreach ([
                'Phone' => $person->phone ? \App\Support\Phone::local($person->phone) : '—',
                'Email' => $person->email ?? '—',
                'Invited by' => $person->inviter?->name ?? '—',
                'Joined' => $person->invite_accepted_at?->format('j M Y') ?? $person->created_at?->format('j M Y'),
            ] as $label => $value)
                <div class="flex justify-between gap-4 border-b border-line py-3"><dt class="text-muted">{{ $label }}</dt><dd class="num truncate font-medium">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </x-card>
</x-layouts.app>
