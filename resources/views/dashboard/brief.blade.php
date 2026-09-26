@php
    $field = $brief['field'];
@endphp
<x-layouts.base title="Daily brief">
    <main id="main" class="mx-auto max-w-4xl px-4 py-8 print:max-w-none print:px-0 print:py-0">
        <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
            <x-button :href="route('dashboard')" variant="ghost" icon="arrow-left">Dashboard</x-button>
            <x-button type="button" icon="printer" onclick="window.print()">Print or save as PDF</x-button>
        </div>

        <header class="flex items-start justify-between gap-4 border-b border-line pb-5">
            <div>
                <p class="eyebrow">{{ $appName }} · daily brief</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $brief['generatedAt']->format('l j F Y') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ max(0, \App\Support\Time::daysToElection()) }} days to election day · prepared {{ $brief['generatedAt']->format('g:i A') }}</p>
            </div>
            <x-logo :size="44" />
        </header>

        <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['Registered today', number_format($field['today'])],
                ['This week', number_format($field['week']).($field['weekDelta'] !== null ? ' ('.($field['weekDelta'] >= 0 ? '+' : '−').abs($field['weekDelta']).'%)' : '')],
                ['In all', number_format($field['total']).' of '.number_format($field['target'])],
                ['Quiet wards', $field['quietWards'].' of '.$field['wards']],
            ] as [$label, $value])
                <div class="rounded-xl border border-line p-3"><p class="text-xs text-muted">{{ $label }}</p><p class="num mt-1 text-lg font-bold">{{ $value }}</p></div>
            @endforeach
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <h2 class="mb-2 flex items-center gap-2 text-base font-semibold"><x-icon name="trending-up" size="18" class="text-good" />Where we’re winning</h2>
                @forelse ($brief['winning'] as $row)
                    <p class="flex justify-between border-b border-line py-1.5 text-sm"><span>{{ $row['name'] }}, {{ $row['lga'] }}</span><span class="num font-semibold">{{ $row['share'] }}%{{ $row['trend'] !== null ? ' ('.($row['trend'] >= 0 ? '+' : '−').abs($row['trend']).')' : '' }}</span></p>
                @empty
                    <p class="text-sm text-muted">No stronghold on current data.</p>
                @endforelse
            </div>
            <div>
                <h2 class="mb-2 flex items-center gap-2 text-base font-semibold"><x-icon name="trending-down" size="18" class="text-bad" />Where we’re losing</h2>
                @forelse ($brief['losing'] as $row)
                    <p class="flex justify-between border-b border-line py-1.5 text-sm"><span>{{ $row['name'] }}, {{ $row['lga'] }} <span class="text-subtle">({{ strtolower(\App\Services\Intelligence::ZONES[$row['zone']]['label']) }})</span></span><span class="num font-semibold">{{ $row['share'] }}%{{ $row['trend'] !== null ? ' ('.($row['trend'] >= 0 ? '+' : '−').abs($row['trend']).')' : '' }}</span></p>
                @empty
                    <p class="text-sm text-muted">No weak or slipping wards on current data.</p>
                @endforelse
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-line p-4">
            <h2 class="mb-2 flex items-center gap-2 text-base font-semibold"><x-icon name="sparkles" size="18" class="text-brand-fg" />What to push next</h2>
            @if ($brief['suggestion'])
                <p class="text-sm"><span class="font-semibold">AI suggestion (not a decision):</span> {{ $brief['suggestion']['text'] }}</p>
            @endif
            <p class="mt-2 text-sm"><span class="font-semibold">Rising issues:</span> {{ $brief['rising']->map(fn ($i) => $i['label'].' ('.$i['week'].')')->implode(', ') ?: 'none this week' }}.</p>
        </section>

        <section class="mt-6 break-inside-avoid">
            @if ($map['ward'])
                <x-ward-map :map="$map['ward']" title="Zones by ward" />
            @else
                <x-lga-map :map="$map['lga']" title="Zones by LGA" />
            @endif
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <h2 class="mb-2 text-base font-semibold">Priority wards</h2>
                @foreach ($brief['priority'] as $row)
                    <p class="flex justify-between border-b border-line py-1.5 text-sm"><span>{{ $row['name'] }}, {{ $row['lga'] }}</span><span class="num">{{ number_format($row['priority']) }}</span></p>
                @endforeach
            </div>
            <div>
                <h2 class="mb-2 text-base font-semibold">Top of the board this week</h2>
                @forelse ($brief['leaders']['agents'] as $row)
                    <p class="flex justify-between border-b border-line py-1.5 text-sm"><span>{{ $row['rank'] }}. {{ $row['user']->name }}, {{ $row['user']->ward?->name }}</span><span class="num">{{ number_format($row['points']) }} pts</span></p>
                @empty
                    <p class="text-sm text-muted">No points yet this week.</p>
                @endforelse
                @if ($brief['leaders']['lga'])<p class="mt-2 text-sm">Top LGA: <strong>{{ $brief['leaders']['lga']['name'] }}</strong>. Top ward: <strong>{{ $brief['leaders']['ward']['name'] ?? '—' }}</strong>.</p>@endif
            </div>
        </section>

        <p class="mt-8 text-xs text-subtle">Zones blend past results, canvassing and surveys (weights in Settings); each ward’s figure says what it rests on. {{ $brief['hasResults'] ? '' : 'No past results are imported yet, so zones rest on canvassing alone.' }}</p>
    </main>
</x-layouts.base>
