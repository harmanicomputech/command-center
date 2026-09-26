<x-layouts.base title="Issues briefing pack">
    <main id="main" class="mx-auto max-w-4xl px-4 py-8 print:max-w-none print:p-0">
        <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
            <x-button :href="route('issues')" variant="ghost" icon="arrow-left">Back to issues</x-button>
            <x-button type="button" icon="printer" onclick="window.print()">Print or save as PDF</x-button>
        </div>
        <header class="mb-8 flex items-center justify-between gap-4 border-b border-line pb-6">
            <div>
                <p class="eyebrow">{{ $appName }} · briefing pack</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">Top issues by LGA</h1>
                <p class="mt-1 text-sm text-muted">From field reports up to {{ $generatedAt->format('l j F Y, g:i A') }}. Counts are reports, not surveys; people affected are the reporters’ estimates.</p>
            </div>
            <x-logo :size="44" />
        </header>

        @forelse ($lgas as $row)
            <section class="card mb-6 p-4 break-inside-avoid sm:p-6">
                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 class="text-xl font-semibold">{{ $row['lga']->name }}</h2>
                    <p class="num text-sm text-muted">{{ number_format($row['total']) }} reports</p>
                </div>
                <div class="table-wrap" tabindex="0"><table class="table">
                    <thead><tr><th>#</th><th>Issue</th><th class="n">Reports</th><th class="n">Serious</th><th class="n">People</th></tr></thead>
                    <tbody>
                        @foreach ($row['top'] as $i => $issue)
                            <tr>
                                <td class="num text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <p class="font-semibold">{{ config('field.issue_categories.'.$issue->category) }}</p>
                                    @foreach ($examples[$row['lga']->id.'|'.$issue->category] ?? [] as $example)
                                        <p class="mt-1 text-xs text-muted">“{{ \Illuminate\Support\Str::limit($example->description, 140) }}” <span class="text-subtle">({{ $example->ward->name }})</span></p>
                                    @endforeach
                                </td>
                                <td class="n">{{ $issue->n }}</td>
                                <td class="n">{{ $issue->serious }}</td>
                                <td class="n">{{ $issue->people ? number_format($issue->people) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </section>
        @empty
            <x-empty icon="file-text" title="Nothing to brief yet" description="The pack fills in as agents report issues." />
        @endforelse
    </main>
</x-layouts.base>
