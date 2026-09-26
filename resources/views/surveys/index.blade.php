<x-layouts.app title="Surveys">
    <x-page-header title="Surveys" eyebrow="Intelligence" description="Ask voters directly: agents in the field (offline), a web link, or quick SMS and USSD polls. Voting-intention answers feed each ward’s zone.">
        <x-slot:actions>
            @if ($canEdit)<x-button :href="route('surveys.create')" icon="plus">New survey</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($surveys as $survey)
            <a href="{{ route('surveys.show', $survey) }}" class="card card-interactive flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid size-10 flex-none place-items-center rounded-xl bg-info-soft text-info"><x-icon name="clipboard-list" size="19" /></span>
                    <x-badge :tone="['live' => 'good', 'draft' => null, 'closed' => 'info'][$survey->status]" dot>{{ ucfirst($survey->status) }}</x-badge>
                </div>
                <p class="mt-4 font-semibold">{{ $survey->title }}</p>
                <p class="mt-1 text-sm text-muted">{{ $survey->targetLabel() }} · {{ collect($survey->channels)->map(fn ($c) => config('surveys.channels.'.$c))->implode(', ') }}</p>
                <div class="mt-auto flex items-end justify-between pt-5">
                    <p><span class="num text-2xl font-bold">{{ number_format($survey->responses_count) }}</span> <span class="text-sm text-muted">responses</span></p>
                    <span class="text-xs text-subtle">{{ $survey->created_at->format('j M') }}</span>
                </div>
            </a>
        @empty
            <div class="card md:col-span-2 xl:col-span-3">
                <x-empty icon="clipboard-list" title="No surveys yet" description="Start with a short one: voting intention and the issue that matters most, asked by agents in the rural wards.">
                    @if ($canEdit)<x-button :href="route('surveys.create')" icon="plus">New survey</x-button>@endif
                </x-empty>
            </div>
        @endforelse
    </div>
</x-layouts.app>
