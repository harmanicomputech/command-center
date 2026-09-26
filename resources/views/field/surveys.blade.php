<x-layouts.field title="Surveys" :back="route('field.home')">
    <h1 class="text-2xl font-bold tracking-tight">Surveys</h1>
    <p class="mt-1 text-sm text-muted">Ask people in your ward. It works offline: answers wait on your phone and send later.</p>
    <div class="mt-5 space-y-3">
        @forelse ($surveys as $survey)
            @php
                $wardCount = (int) ($counts[$survey->id] ?? 0);
                $full = $survey->quota_per_ward && $wardCount >= $survey->quota_per_ward;
            @endphp
            <a href="{{ $full ? '#' : route('field.survey', $survey) }}" class="card card-interactive block p-4 {{ $full ? 'pointer-events-none opacity-60' : '' }}">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 flex-none place-items-center rounded-xl bg-info-soft text-info"><x-icon name="clipboard-list" size="20" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $survey->title }}</p>
                        <p class="text-sm text-muted">{{ $survey->questions->count() }} questions · you’ve asked {{ (int) ($mine[$survey->id] ?? 0) }}</p>
                        @if ($survey->quota_per_ward)
                            <div class="mt-2 flex items-center gap-2 text-xs text-muted"><x-progress :value="$wardCount" :max="$survey->quota_per_ward" class="flex-1" /><span class="num">{{ $wardCount }}/{{ $survey->quota_per_ward }} in your ward</span></div>
                        @endif
                        @if ($full)<x-badge tone="good" class="mt-2">Your ward’s quota is met</x-badge>@endif
                    </div>
                    <x-icon name="chevron-right" class="mt-2 text-subtle" />
                </div>
            </a>
        @empty
            <div class="card"><x-empty icon="clipboard-list" title="No surveys running" description="When the campaign launches a survey in your ward, it appears here." compact /></div>
        @endforelse
    </div>
</x-layouts.field>
