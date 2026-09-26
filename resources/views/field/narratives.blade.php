<x-layouts.field title="What people are saying" :back="route('field.home')">
    <div x-data="narrativeForm()">
        <section x-show="saved" x-cloak class="pt-6 text-center">
            <div class="mx-auto mb-5 grid size-20 place-items-center rounded-full bg-brand text-on-brand shadow-raised"><x-icon name="check" size="34" /></div>
            <h1 class="text-2xl font-bold tracking-tight">Thank you</h1>
            <p class="mx-auto mt-2 max-w-xs text-sm text-muted">It goes to the media team when there’s network. They watch what spreads and decide how to answer.</p>
            <div class="mt-8 flex flex-col gap-3">
                <x-button type="button" size="xl" icon="plus" class="w-full" x-on:click="another()">Report another</x-button>
                <x-button :href="route('field.home')" variant="secondary" size="lg" class="w-full">Done</x-button>
            </div>
        </section>

        <form x-show="! saved" x-ref="form" x-on:submit="submit($event)" class="space-y-7" novalidate>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">What are people saying?</h1>
                <p class="mt-1 text-sm text-muted">A rumour, a complaint, something the other side is spreading, or good news. Report it; don’t argue it.</p>
            </div>
            <div>
                <label for="n-summary" class="label">What did you hear or see?</label>
                <textarea id="n-summary" name="summary" class="input" rows="4" maxlength="2000" placeholder="e.g. People at the market say the candidate will increase stall fees"></textarea>
                <p class="field-error" x-show="errors.summary" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.summary?.[0]"></span></p>
            </div>
            <div>
                <x-segmented name="source" label="Where?" :options="config('messaging.sources')" :cols="2" />
                <p class="field-error" x-show="errors.source" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.source?.[0]"></span></p>
            </div>
            <div>
                <x-segmented name="tone" label="Is it good or bad for us?" :options="collect(config('messaging.narrative_tones'))->map(fn ($t) => $t['label'])->all()" :cols="3" />
                <p class="field-error" x-show="errors.tone" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.tone?.[0]"></span></p>
            </div>
            <div>
                <x-select name="topic" label="About" :options="config('messaging.narrative_topics')" placeholder="Choose…" />
                <p class="field-error" x-show="errors.topic" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.topic?.[0]"></span></p>
            </div>
            <section class="card space-y-5 p-4">
                <x-select name="ward_id" label="Ward where you heard it" :options="$wards" :value="$defaultWard" />
                <div>
                    <label for="n-link" class="label">Link <span class="font-normal text-subtle">(if it was online)</span></label>
                    <input id="n-link" name="link" type="url" inputmode="url" class="input" placeholder="https://">
                    <p class="field-error" x-show="errors.link" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.link?.[0]"></span></p>
                </div>
            </section>
            <x-photo-field label="Screenshot or photo" />
            <x-button size="xl" icon="send" class="w-full">Send</x-button>
        </form>

        @if ($mine->isNotEmpty())
            <section x-show="! saved" class="mt-10">
                <h2 class="mb-3 text-base font-semibold">My reports</h2>
                <div class="card divide-y divide-line">
                    @foreach ($mine as $report)
                        <div class="flex items-center gap-3 p-4">
                            <span class="grid size-12 flex-none place-items-center rounded-lg bg-surface-2 text-subtle"><x-icon name="radio" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ $report->summary }}</p>
                                <p class="text-xs text-subtle">{{ $report->sourceLabel() }} · {{ $report->seen_at->diffForHumans() }}</p>
                            </div>
                            <x-badge :tone="$report->toneTone()">{{ $report->toneLabel() }}</x-badge>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.field>
