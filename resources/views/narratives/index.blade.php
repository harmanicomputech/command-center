@php
    $tabs = [['Active', route('narratives'), ! $status]];
    foreach (config('messaging.narrative_statuses') as $key => $option) {
        $tabs[] = [$option['label'], route('narratives', ['status' => $key]), $status === $key];
    }
@endphp
<x-layouts.app title="Narratives" wide>
    <x-page-header title="Narratives" eyebrow="Engage" description="What people are saying, reported by agents and the media team. Group reports that tell the same story, watch the trend, and decide when to respond.">
        <x-slot:actions>
            @if ($aiReady && $inbox->count() >= 2)
                <form method="post" action="{{ route('narratives.suggest') }}">@csrf<x-button variant="secondary" icon="sparkles">Suggest groups</x-button></form>
            @endif
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'report-new')">Add a report</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi label="Reports this week" :value="$counts['week']" icon="radio" />
        <x-kpi label="Negative this week" :value="$counts['negative']" icon="trending-down" />
        <x-kpi label="Being responded to" :value="$counts['responding']" icon="megaphone" :href="route('narratives', ['status' => 'responding'])" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
        <section class="min-w-0">
            <x-tabs :items="$tabs" />
            <div class="grid gap-3">
                @forelse ($narratives as $narrative)
                    <a href="{{ route('narratives.show', $narrative) }}" class="card card-interactive flex items-center gap-4 p-4 sm:p-5">
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-1.5">
                                <x-badge :tone="$narrative->statusTone()" dot>{{ $narrative->statusLabel() }}</x-badge>
                                <x-badge :tone="$narrative->toneTone()">{{ $narrative->toneLabel() }}</x-badge>
                                <x-badge>{{ $narrative->topicLabel() }}</x-badge>
                            </span>
                            <span class="mt-2 line-clamp-2 block font-semibold">{{ $narrative->title }}</span>
                            <span class="num mt-1 block text-xs text-subtle">{{ number_format($narrative->reports_count) }} {{ Str::plural('report', $narrative->reports_count) }} · {{ number_format($narrative->week) }} this week</span>
                        </span>
                        <span class="flex-none text-right">
                            <x-sparkline :values="$trends[$narrative->id] ?? []" :tone="$narrative->tone === 'negative' ? 'bad' : 'brand'" label="Reports per day, last 14 days" />
                            <span class="mt-1 block text-[11px] text-subtle">14 days</span>
                        </span>
                    </a>
                @empty
                    <div class="card"><x-empty icon="radio" title="No narratives here" description="Group reports from the inbox into a narrative to follow its trend." /></div>
                @endforelse
            </div>
        </section>

        <section class="min-w-0 space-y-4">
            @if ($suggestions['status'] === 'working')
                <div class="card flex items-center gap-3 p-4" x-data="waitFor(@js(route('narratives.suggest.status')))">
                    <x-icon name="loader-circle" class="animate-spin text-brand-fg motion-reduce:animate-none" />
                    <p class="text-sm">Claude is reading the inbox… <span class="num text-subtle" x-text="seconds + 's'"></span></p>
                </div>
            @elseif ($suggestions['status'] === 'failed')
                <x-alert tone="bad" title="No suggestions">{{ $suggestions['error'] ?? 'Something went wrong.' }}</x-alert>
            @elseif (count($suggestions['groups']) > 0)
                <section class="card overflow-hidden">
                    <header class="flex items-center justify-between gap-3 border-b border-line bg-brand-softer px-5 py-3">
                        <p class="flex items-center gap-2 text-sm font-semibold text-brand-fg"><x-icon name="sparkles" size="16" />AI suggestions: check before accepting</p>
                        <form method="post" action="{{ route('narratives.suggest.dismiss') }}">@csrf @method('delete')<button class="text-xs font-medium text-muted hover:text-ink">Dismiss</button></form>
                    </header>
                    <div class="divide-y divide-line">
                        @foreach ($suggestions['groups'] as $group)
                            @php $target = $group['narrative_id'] ? $open->firstWhere('id', $group['narrative_id']) : null; @endphp
                            <form method="post" action="{{ route('narratives.group') }}" class="p-5">
                                @csrf
                                @foreach ($group['report_ids'] as $id)<input type="hidden" name="report_ids[]" value="{{ $id }}">@endforeach
                                @if ($target)
                                    <input type="hidden" name="narrative_id" value="{{ $target->id }}">
                                    <p class="text-xs text-muted">Add {{ count($group['report_ids']) }} to</p>
                                    <p class="font-semibold">{{ $target->title }}</p>
                                @else
                                    <p class="text-xs text-muted">New narrative from {{ count($group['report_ids']) }} reports</p>
                                    <label class="sr-only" for="sg-{{ $loop->index }}">Title</label>
                                    <input id="sg-{{ $loop->index }}" name="title" value="{{ $group['title'] }}" class="input mt-1 font-semibold">
                                @endif
                                @if ($group['why'])<p class="mt-2 text-sm text-muted">{{ $group['why'] }}</p>@endif
                                <ul class="mt-3 space-y-1.5">
                                    @foreach ($group['report_ids'] as $id)
                                        @if ($suggestedReports->has($id))<li class="line-clamp-2 border-l-2 border-line-strong pl-3 text-sm">{{ $suggestedReports[$id]->summary }}</li>@endif
                                    @endforeach
                                </ul>
                                <x-button size="sm" icon="check" class="mt-3">Accept</x-button>
                            </form>
                        @endforeach
                    </div>
                </section>
            @endif

            <form method="post" action="{{ route('narratives.group') }}" class="card overflow-hidden" x-data="{ picked: [] }">
                @csrf
                <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold">Inbox</h2>
                        <p class="text-sm text-muted">Reports not in a narrative yet</p>
                    </div>
                    <x-badge>{{ $inbox->count() }}</x-badge>
                </header>
                <div class="max-h-[640px] divide-y divide-line overflow-y-auto">
                    @forelse ($inbox as $report)
                        <label class="flex cursor-pointer gap-3 px-5 py-4 hover:bg-surface-2">
                            <input type="checkbox" name="report_ids[]" value="{{ $report->id }}" x-model="picked" class="checkbox mt-0.5">
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-3 block text-sm">{{ $report->summary }}</span>
                                <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <x-badge :tone="$report->toneTone()">{{ $report->toneLabel() }}</x-badge>
                                    <x-badge>{{ $report->sourceLabel() }}</x-badge>
                                    @if ($report->photos->isNotEmpty())<x-badge icon="eye">Photo</x-badge>@endif
                                    @if ($report->link)<x-badge icon="external-link">Link</x-badge>@endif
                                </span>
                                <span class="mt-1 block text-xs text-subtle">{{ $report->place() }} · {{ $report->seen_at->diffForHumans() }}</span>
                            </span>
                        </label>
                    @empty
                        <x-empty icon="inbox" title="Inbox clear" description="New reports from agents land here." compact />
                    @endforelse
                </div>
                @if ($inbox->isNotEmpty())
                    <footer class="space-y-3 border-t border-line bg-surface-2 p-4" x-show="picked.length" x-cloak>
                        <p class="text-sm font-medium"><span x-text="picked.length"></span> selected</p>
                        <select name="narrative_id" class="input" aria-label="Add to a narrative" x-ref="target">
                            <option value="">A new narrative…</option>
                            @foreach ($open as $option)<option value="{{ $option->id }}">{{ Str::limit($option->title, 60) }}</option>@endforeach
                        </select>
                        <input name="title" class="input" placeholder="New narrative title" aria-label="New narrative title">
                        <x-button icon="layers" class="w-full">Group</x-button>
                    </footer>
                @endif
            </form>
        </section>
    </div>

    <x-modal name="report-new" title="Add a report" width="max-w-xl">
        @include('narratives._report-form')
    </x-modal>
</x-layouts.app>
