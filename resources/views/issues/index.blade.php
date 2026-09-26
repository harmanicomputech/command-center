@php
    $maxCategory = max(1, (int) $byCategory->max('n'));
    $query = fn (array $change) => route('issues', array_filter([...request()->only('category', 'status', 'lga'), ...$change]));
@endphp
<x-layouts.app title="Issues" wide>
    <x-page-header title="Issues" eyebrow="Field" description="What communities are struggling with, reported from the field. Use them in speeches and messages, and mark them as they are used or addressed.">
        <x-slot:actions>
            <x-button :href="route('issues.brief')" variant="secondary" icon="printer">Briefing pack</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <x-lga-map :map="$map" title="Issues by LGA" description="Reports in each LGA (rejected ones left out)." class="xl:col-span-2" />

        <div class="grid content-start gap-6 xl:col-span-3">
            <div class="grid grid-cols-2 gap-4">
                <x-kpi label="Issues reported" :value="$total" icon="triangle-alert" :spark="$weekly" hint="Last 8 weeks" />
                <x-kpi label="People affected (estimates)" :value="(int) $byCategory->sum('people')" icon="users" />
            </div>
            <x-card title="By category" icon="chart-column">
                @forelse ($byCategory as $row)
                    <a href="{{ $query(['category' => $row->category]) }}" class="group -mx-2 grid grid-cols-[120px_minmax(0,1fr)_auto] items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-surface-2 sm:grid-cols-[150px_minmax(0,1fr)_auto]">
                        <span class="truncate text-sm font-medium">{{ config('field.issue_categories.'.$row->category) }}</span>
                        <span class="h-2.5 overflow-hidden rounded-full bg-surface-3"><span class="block h-full rounded-full bg-brand" style="width: {{ round(100 * $row->n / $maxCategory) }}%"></span></span>
                        <span class="num w-10 text-right text-sm font-semibold">{{ $row->n }}</span>
                    </a>
                @empty
                    <x-empty icon="chart-column" title="No issues reported yet" description="When agents report issues from their phones, the biggest ones rise to the top here." compact />
                @endforelse
            </x-card>
        </div>
    </div>

    <div class="mt-8 mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="no-scrollbar -mx-4 flex gap-1.5 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <a href="{{ $query(['status' => null]) }}" class="btn btn-sm {{ $status ? 'btn-ghost' : 'btn-secondary' }}">All</a>
            @foreach (config('field.issue_statuses') as $key => $option)
                <a href="{{ $query(['status' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn-secondary' : 'btn-ghost' }}">{{ $option['label'] }}</a>
            @endforeach
        </div>
        <form method="get" action="{{ route('issues') }}" class="flex gap-2">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <select name="category" class="input w-44" aria-label="Category" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach (config('field.issue_categories') as $key => $label)<option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>@endforeach
            </select>
            <select name="lga" class="input w-44" aria-label="LGA" onchange="this.form.submit()">
                <option value="">All LGAs</option>
                @foreach ($lgas as $option)<option value="{{ $option->slug }}" @selected($lga?->id === $option->id)>{{ $option->name }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
        @forelse ($issues as $issue)
            <article class="card flex flex-col overflow-hidden">
                @if ($issue->photos->isNotEmpty())
                    <a href="{{ $issue->photos->first()->url() }}" target="_blank" class="block aspect-[16/9] bg-surface-2"><img src="{{ $issue->photos->first()->url(true) }}" alt="Photo of the issue" class="size-full object-cover" loading="lazy"></a>
                @endif
                <div class="flex flex-1 flex-col p-5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <x-badge tone="brand">{{ $issue->categoryLabel() }}</x-badge>
                        <x-badge :tone="$issue->severityTone()" dot>{{ $issue->severityLabel() }}</x-badge>
                        @if ($issue->people_affected)<x-badge icon="users">{{ number_format($issue->people_affected) }}</x-badge>@endif
                    </div>
                    <p class="mt-3 line-clamp-4 text-sm">{{ $issue->description }}</p>
                    <p class="mt-3 text-xs text-subtle">{{ $issue->ward->fullName() }}{{ $issue->community ? ' · '.$issue->community : '' }} · {{ $issue->reporter?->name ?? 'Unknown' }} · {{ $issue->reported_at->diffForHumans() }}</p>
                    <form method="post" action="{{ route('issues.status', $issue) }}" class="mt-auto pt-4">
                        @csrf
                        <label class="sr-only" for="st-{{ $issue->id }}">Status</label>
                        <select id="st-{{ $issue->id }}" name="status" class="input" onchange="this.form.submit()">
                            @foreach (config('field.issue_statuses') as $key => $option)<option value="{{ $key }}" @selected($issue->status === $key)>{{ $option['label'] }}</option>@endforeach
                        </select>
                    </form>
                </div>
            </article>
        @empty
            <div class="card md:col-span-2 2xl:col-span-3"><x-empty icon="triangle-alert" title="No issues match" description="Agents report issues from the Issues tab in the field app." /></div>
        @endforelse
    </div>
    @if ($issues->hasPages())<div class="mt-4">{{ $issues->links() }}</div>@endif
</x-layouts.app>
