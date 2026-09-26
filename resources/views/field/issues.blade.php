<x-layouts.field title="Report an issue">
    <div x-data="issueForm()">
        <section x-show="saved" x-cloak class="pt-6 text-center">
            <div class="mx-auto mb-5 grid size-20 place-items-center rounded-full bg-brand text-on-brand shadow-raised"><x-icon name="check" size="34" /></div>
            <h1 class="text-2xl font-bold tracking-tight">Issue reported</h1>
            <p class="mx-auto mt-2 max-w-xs text-sm text-muted"><span x-text="saved"></span>. It goes to the campaign when there’s network: +{{ \App\Support\Settings::int('points.issue') }} points once it’s accepted.</p>
            <div class="mt-8 flex flex-col gap-3">
                <x-button type="button" size="xl" icon="plus" class="w-full" x-on:click="another()">Report another</x-button>
                <x-button :href="route('field.home')" variant="secondary" size="lg" class="w-full">Done</x-button>
            </div>
        </section>

        <form x-show="! saved" x-ref="form" x-on:submit="submit($event)" class="space-y-7" novalidate>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Report an issue</h1>
                <p class="mt-1 text-sm text-muted">What people in the community are struggling with. It shapes what the candidate promises.</p>
            </div>
            <div>
                <x-segmented name="category" label="What kind of issue?" :options="config('field.issue_categories')" :cols="2" />
                <p class="field-error" x-show="errors.category" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.category?.[0]"></span></p>
            </div>
            <div>
                <label for="i-description" class="label">What’s wrong?</label>
                <textarea id="i-description" name="description" class="input" rows="4" maxlength="2000" placeholder="e.g. The road from the market to the health centre is cut by erosion"></textarea>
                <p class="field-error" x-show="errors.description" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.description?.[0]"></span></p>
            </div>
            <div>
                <x-segmented name="severity" label="How serious?" :options="collect(config('field.severities'))->map(fn ($s) => $s['label'])->all()" :cols="2" />
                <p class="field-error" x-show="errors.severity" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.severity?.[0]"></span></p>
            </div>
            <div>
                <label for="i-people" class="label">About how many people are affected? <span class="font-normal text-subtle">(a guess is fine)</span></label>
                <input id="i-people" name="people_affected" type="number" inputmode="numeric" min="0" class="input">
            </div>
            <section class="card space-y-5 p-4">
                <p class="flex items-center gap-2 text-sm font-semibold"><x-icon name="map-pin" size="18" class="text-brand-fg" /> Where</p>
                <x-select name="ward_id" label="Ward" :options="$wards" :value="$defaultWard" />
                <div>
                    <label for="i-community" class="label">Community or village <span class="font-normal text-subtle">(optional)</span></label>
                    <input id="i-community" name="community" class="input" autocapitalize="words">
                </div>
            </section>
            <x-photo-field />
            <x-button size="xl" icon="send" class="w-full">Send report</x-button>
        </form>

        @if ($mine->isNotEmpty())
            <section x-show="! saved" class="mt-10">
                <h2 class="mb-3 text-base font-semibold">My reports</h2>
                <div class="card divide-y divide-line">
                    @foreach ($mine as $issue)
                        <div class="flex items-center gap-3 p-4">
                            @if ($issue->photos->isNotEmpty())
                                <img src="{{ $issue->photos->first()->url(true) }}" alt="" class="size-12 flex-none rounded-lg object-cover" loading="lazy">
                            @else
                                <span class="grid size-12 flex-none place-items-center rounded-lg bg-surface-2 text-subtle"><x-icon name="triangle-alert" /></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ $issue->categoryLabel() }}{{ $issue->community ? ', '.$issue->community : '' }}</p>
                                <p class="text-xs text-subtle">{{ $issue->reported_at->diffForHumans() }}</p>
                            </div>
                            <x-badge :tone="$issue->statusTone()">{{ $issue->statusLabel() }}</x-badge>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.field>
