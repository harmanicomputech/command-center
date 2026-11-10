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

    @if ($faces->isNotEmpty())
        <x-card class="mt-6" title="Recent face photos" description="From this person’s latest registrations. The same face on different names is a warning sign: spot-check those records." icon="scan-face">
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6 lg:grid-cols-8">
                @foreach ($faces as $voter)
                    <a href="{{ $voter->facePhoto->url() }}" target="_blank" class="group min-w-0" data-no-progress>
                        <img src="{{ $voter->facePhoto->url(true) }}" alt="Face photo of {{ $voter->name }}" class="aspect-square w-full rounded-xl border border-line object-cover transition group-hover:border-line-strong" loading="lazy">
                        <span class="mt-1 block truncate text-xs text-muted">{{ $voter->name }}</span>
                    </a>
                @endforeach
            </div>
        </x-card>
    @endif

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
