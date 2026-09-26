@php
    $selected = fn (string $key, $value) => in_array((string) $value, array_map('strval', $filters[$key] ?? []), true);
    $total = max(1, $result['count']);
@endphp
<x-layouts.app title="Segments" wide>
    <x-page-header title="Segments" eyebrow="Intelligence" description="Groups of canvassed voters by age, occupation, gender, area, support and top issue. Counts only: a segment describes a group, never lists people.">
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
        <form method="get" action="{{ route('segments') }}" class="card space-y-6 p-5 xl:sticky xl:top-24 xl:self-start" x-data x-on:change="$el.requestSubmit()">
            @foreach (\App\Services\Segments::DIMENSIONS as $key => [$title, $config])
                <fieldset>
                    <legend class="eyebrow mb-2">{{ $title }}</legend>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (config($config) as $value => $optionName)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="{{ $key }}[]" value="{{ $value }}" class="peer sr-only" @checked($selected($key, $value))>
                                <span class="inline-flex h-8 items-center rounded-full border border-line-strong px-3 text-[13px] font-medium transition-colors peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand-fg peer-focus-visible:outline-2 peer-focus-visible:outline-brand hover:bg-surface-2">{{ is_array($optionName) ? $optionName['label'] : $optionName }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <fieldset>
                <legend class="eyebrow mb-2">LGA</legend>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($lgas as $lga)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="lga_id[]" value="{{ $lga->id }}" class="peer sr-only" @checked($selected('lga_id', $lga->id))>
                            <span class="inline-flex h-8 items-center rounded-full border border-line-strong px-3 text-[13px] font-medium transition-colors peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand-fg hover:bg-surface-2">{{ $lga->name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex gap-2">
                <noscript><x-button icon="funnel">Apply</x-button></noscript>
                <x-button :href="route('segments')" variant="ghost" size="sm" icon="x">Clear all</x-button>
            </div>
        </form>

        <div class="min-w-0 space-y-6">
            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-end justify-between gap-4 p-5 sm:p-6">
                    <div class="min-w-0">
                        <p class="text-sm text-muted">{{ $label }}</p>
                        <p class="num mt-1 text-4xl font-bold tracking-tight"><span x-data x-count="{{ $result['count'] }}">{{ number_format($result['count']) }}</span> <span class="text-lg font-medium text-muted">voters</span></p>
                        <p class="mt-1 text-sm text-muted"><span class="num font-semibold text-ink">{{ number_format($result['with_phone']) }}</span> reachable by SMS (a phone, not opted out)</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-button type="button" variant="secondary" icon="check" x-data x-on:click="$dispatch('open-modal', 'save-segment')">Save segment</x-button>
                        @if ($canMessage)
                            <x-button :href="route('messages.create', ['filters' => $filters])" icon="sparkles">Draft a message</x-button>
                        @endif
                    </div>
                </div>
                @if ($result['count'] > 0 && $result['count'] < 30)
                    <p class="border-t border-line bg-warn-soft px-6 py-2.5 text-sm text-warn">Small sample (n = {{ $result['count'] }}): treat the breakdowns as indicative.</p>
                @endif
            </section>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @foreach (\App\Services\Segments::DIMENSIONS as $key => [$title, $config])
                    <x-card :title="$title">
                        @php $rows = in_array($key, ['age_band', 'support_level'], true) ? collect(config($config))->keys()->filter(fn ($k) => isset($result['breakdowns'][$key][$k]))->mapWithKeys(fn ($k) => [$k => $result['breakdowns'][$key][$k]]) : collect($result['breakdowns'][$key])->sortDesc(); @endphp
                        @forelse ($rows as $value => $n)
                            @php $optionLabel = config("{$config}.{$value}"); @endphp
                            <div class="mb-2.5 last:mb-0">
                                <div class="mb-1 flex justify-between gap-3 text-sm"><span>{{ is_array($optionLabel) ? $optionLabel['label'] : ($optionLabel ?? $value) }}</span><span class="num text-muted">{{ number_format($n) }} · {{ round(100 * $n / $total) }}%</span></div>
                                <x-progress :value="$n" :max="$total" />
                            </div>
                        @empty
                            <p class="text-sm text-muted">No data.</p>
                        @endforelse
                    </x-card>
                @endforeach
                <x-card title="By LGA">
                    @forelse ($result['lgas'] as $name => $n)
                        <div class="mb-2.5 last:mb-0">
                            <div class="mb-1 flex justify-between gap-3 text-sm"><span>{{ $name }}</span><span class="num text-muted">{{ number_format($n) }}</span></div>
                            <x-progress :value="$n" :max="$total" />
                        </div>
                    @empty
                        <p class="text-sm text-muted">No data.</p>
                    @endforelse
                </x-card>
            </div>

            <x-table title="Saved segments">
                <thead><tr><th>Name</th><th>Filters</th><th></th></tr></thead>
                <tbody>
                    @forelse ($saved as $segment)
                        <tr>
                            <td class="font-semibold whitespace-nowrap"><a href="{{ route('segments', $segment->filters) }}" class="hover:text-brand-fg">{{ $segment->name }}</a></td>
                            <td class="text-sm text-muted">{{ app(\App\Services\Segments::class)->label($segment->filters) }}</td>
                            <td class="text-right"><form method="post" action="{{ route('segments.destroy', $segment) }}">@csrf @method('delete')<x-button variant="ghost" size="sm" square icon="trash-2" aria-label="Delete {{ $segment->name }}" /></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-sm text-muted">None saved yet.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </div>
    </div>

    <x-modal name="save-segment" title="Save this segment">
        <form method="post" action="{{ route('segments.store') }}" class="space-y-5">
            @csrf
            @foreach ($filters as $key => $values)
                @foreach ($values as $value)<input type="hidden" name="filters[{{ $key }}][]" value="{{ $value }}">@endforeach
            @endforeach
            <p class="text-sm text-muted">{{ $label }}</p>
            <x-input name="name" label="Name" placeholder="e.g. Young traders in Abakaliki" required />
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="check">Save</x-button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>
