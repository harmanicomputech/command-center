<x-layouts.app title="New message">
    <x-page-header title="New message" eyebrow="Messages" :back="route('messages')" description="Claude drafts three versions. It sees the segment’s description and counts, the policy brief, and the top issues reported in the area, never anyone’s name or number." />

    @unless ($configured)
        <x-alert tone="warn" title="AI drafting isn’t set up yet" class="mb-6">Add the Claude API key on the System page first.</x-alert>
    @endunless
    @if ($policies === 0)
        <x-alert tone="info" title="The policy brief is empty" class="mb-6">
            Drafts stay general until the campaign writes its positions. <a href="{{ route('policies') }}" class="font-semibold text-brand-fg underline">Add policy briefs</a>.
        </x-alert>
    @endif

    <form id="pick-segment" method="get" action="{{ route('messages.create') }}"></form>
    <form method="post" action="{{ route('messages.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        @csrf
        @foreach ($filters as $key => $values)
            @foreach ($values as $value)<input type="hidden" name="filters[{{ $key }}][]" value="{{ $value }}">@endforeach
        @endforeach
        <input type="hidden" name="label" value="{{ $label }}">

        <div class="min-w-0 space-y-6">
            <x-card title="Goal" icon="target">
                <x-textarea name="goal" label="What should this message achieve?" :value="old('goal', $goal)" rows="3" placeholder="e.g. Tell farmers in Izzi about the fertiliser plan and invite them to Saturday’s town hall" hint="Plain words are best. Numbers and names of people are removed before the goal goes to the AI." />
            </x-card>
            <x-card title="Channel" icon="send">
                <x-segmented name="channel" :options="collect(config('messaging.channels'))->map(fn ($c) => $c['label'])->all()" :value="old('channel', 'sms')" :cols="3" />
                <p class="mt-2 text-xs text-subtle">SMS drafts fit 160 characters; Igbo letters (ị, ọ, ụ) need Unicode SMS, which fits 70 per part.</p>
            </x-card>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-card title="Language" icon="message-square">
                    <x-segmented name="language" :options="config('messaging.languages')" :value="old('language', 'en')" :cols="1" />
                </x-card>
                <x-card title="Tone" icon="sparkle">
                    <x-segmented name="tone" :options="config('messaging.tones')" :value="old('tone', 'hopeful')" :cols="1" />
                </x-card>
            </div>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
            <section class="card p-5">
                <p class="eyebrow mb-2">Audience</p>
                <p class="font-semibold">{{ $label }}</p>
                <p class="num mt-3 text-3xl font-bold tracking-tight">{{ number_format($summary['count']) }}</p>
                <p class="text-sm text-muted">canvassed voters · {{ number_format($summary['with_phone']) }} reachable by SMS</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button :href="route('segments', $filters)" variant="secondary" size="sm" icon="funnel">Change on Segments</x-button>
                </div>
                @if ($saved->isNotEmpty())
                    <div class="mt-4 border-t border-line pt-4">
                        <label for="saved-segment" class="label">Or use a saved segment</label>
                        <select id="saved-segment" name="segment" form="pick-segment" class="input" onchange="this.form.submit()">
                            <option value="">Choose…</option>
                            @foreach ($saved as $option)<option value="{{ $option->id }}" @selected($segment?->id === $option->id)>{{ $option->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </section>
            <x-button size="lg" icon="sparkles" class="w-full" :disabled="! $configured">Draft 3 versions</x-button>
            <p class="text-center text-xs text-subtle">Takes about a minute. Every draft is logged with its cost.</p>
        </aside>
    </form>
</x-layouts.app>
