@php
    $maxSource = max(1, (int) $bySource->max());
    $maxLga = max(1, (int) $byLga->max());
@endphp
<x-layouts.app title="Narrative">
    <x-page-header :title="$narrative->title" eyebrow="Narratives" :back="route('narratives')">
        <x-slot:actions>
            @if ($canDraft)
                <x-button :href="route('messages.create', ['goal' => 'Respond to what people are saying: '.$narrative->title])" variant="secondary" icon="sparkles">Draft a response</x-button>
            @endif
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'report-new')">Add a report</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-6">
            <section class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-1.5">
                    <x-badge :tone="$narrative->toneTone()">{{ $narrative->toneLabel() }}</x-badge>
                    <x-badge>{{ $narrative->topicLabel() }}</x-badge>
                    <span class="num text-xs text-subtle">{{ $reports->count() }} {{ Str::plural('report', $reports->count()) }} · first seen {{ $reports->min('seen_at')?->diffForHumans() ?? '—' }}</span>
                </div>
                @if ($narrative->summary)<p class="mt-3 text-sm whitespace-pre-line">{{ $narrative->summary }}</p>@endif
                <div class="mt-5">
                    <p class="mb-2 text-xs font-medium text-muted">Reports per day, last 30 days</p>
                    <x-sparkline :values="$trend" :width="640" :height="72" :tone="$narrative->tone === 'negative' ? 'bad' : 'brand'" label="Reports per day, last 30 days" class="h-[72px] w-full" />
                </div>
            </section>

            <section>
                <h2 class="mb-3 text-base font-semibold">Reports</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ($reports as $report)
                        <article class="card flex flex-col overflow-hidden">
                            @if ($report->photos->isNotEmpty())
                                <a href="{{ $report->photos->first()->url() }}" target="_blank" class="block aspect-[16/9] bg-surface-2"><img src="{{ $report->photos->first()->url(true) }}" alt="Screenshot for this report" class="size-full object-cover" loading="lazy"></a>
                            @endif
                            <div class="flex flex-1 flex-col p-5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <x-badge>{{ $report->sourceLabel() }}</x-badge>
                                    <x-badge :tone="$report->toneTone()">{{ $report->toneLabel() }}</x-badge>
                                </div>
                                <p class="mt-3 text-sm">{{ $report->summary }}</p>
                                @if ($report->link)<a href="{{ $report->link }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 truncate text-sm font-medium text-brand-fg"><x-icon name="external-link" size="14" />{{ parse_url($report->link, PHP_URL_HOST) }}</a>@endif
                                <p class="mt-3 text-xs text-subtle">{{ $report->place() }} · {{ $report->reporter?->name ?? 'Unknown' }} · {{ $report->seen_at->diffForHumans() }}</p>
                                <div class="mt-auto flex flex-wrap gap-2 pt-4">
                                    @if ($report->photos->isEmpty())
                                        <form method="post" action="{{ route('narratives.reports.photo', $report) }}" enctype="multipart/form-data" x-data>
                                            @csrf
                                            <label class="btn btn-ghost btn-sm cursor-pointer"><x-icon name="upload" />Screenshot<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="$el.form.submit()"></label>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('narratives.reports.ungroup', $report) }}">@csrf<x-button variant="ghost" size="sm" icon="rotate-ccw">Back to inbox</x-button></form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <x-card title="Status" icon="flag">
                <form method="post" action="{{ route('narratives.update', $narrative) }}" x-data x-on:change="$el.requestSubmit()">
                    @csrf @method('put')
                    <x-segmented name="status" :options="collect(config('messaging.narrative_statuses'))->map(fn ($s) => $s['label'])->all()" :value="$narrative->status" :cols="2" />
                    <noscript><x-button size="sm" class="mt-3">Save</x-button></noscript>
                </form>
            </x-card>
            <x-card title="Where it’s seen" icon="radio">
                @foreach ($bySource as $source => $n)
                    <div class="mb-2.5 last:mb-0">
                        <div class="mb-1 flex justify-between text-sm"><span>{{ config('messaging.sources.'.$source, $source) }}</span><span class="num text-muted">{{ $n }}</span></div>
                        <x-progress :value="$n" :max="$maxSource" />
                    </div>
                @endforeach
            </x-card>
            <x-card title="By LGA" icon="map-pin">
                @foreach ($byLga as $name => $n)
                    <div class="mb-2.5 last:mb-0">
                        <div class="mb-1 flex justify-between text-sm"><span>{{ $name }}</span><span class="num text-muted">{{ $n }}</span></div>
                        <x-progress :value="$n" :max="$maxLga" />
                    </div>
                @endforeach
            </x-card>
            <x-card title="Details" icon="pencil">
                <form method="post" action="{{ route('narratives.update', $narrative) }}" class="space-y-4">
                    @csrf @method('put')
                    <x-input name="title" label="Title" :value="$narrative->title" required />
                    <x-textarea name="summary" label="Summary" :value="$narrative->summary" rows="3" optional />
                    <div class="grid grid-cols-2 gap-3">
                        <x-select name="topic" label="Topic" :options="config('messaging.narrative_topics')" :value="$narrative->topic" />
                        <x-select name="tone" label="Tone" :options="collect(config('messaging.narrative_tones'))->map(fn ($t) => $t['label'])->all()" :value="$narrative->tone" />
                    </div>
                    <x-button size="sm" variant="secondary" icon="check">Save</x-button>
                </form>
            </x-card>
        </aside>
    </div>

    <x-modal name="report-new" title="Add a report to this narrative" width="max-w-xl">
        @include('narratives._report-form', ['narrativeId' => $narrative->id, 'lgas' => \App\Models\Lga::query()->visibleTo(auth()->user())->orderBy('name')->get(['id', 'name']), 'wards' => \App\Models\Ward::query()->visibleTo(auth()->user())->with('lga:id,name')->orderBy('name')->get(['id', 'name', 'lga_id'])])
    </x-modal>
</x-layouts.app>
