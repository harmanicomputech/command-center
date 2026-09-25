{{-- Voter registration: under a minute, one-handed, works offline (saved to the outbox first). --}}
@php
    $supportLevels = config('canvass.support_levels');
    $supportDots = ['strong' => 'var(--div-strong)', 'leaning' => 'var(--div-lean)', 'undecided' => 'var(--div-swing)', 'leaning_opponent' => 'var(--div-weakish)', 'opponent' => 'var(--div-weak)'];
    $options = ['wardId' => $defaultWard, 'wards' => $wards, 'units' => $units];
@endphp
<x-layouts.field title="Register a voter">
    <div x-data="registerForm(@js($options))">
        {{-- Success --}}
        <section x-show="saved" x-cloak class="pt-6 text-center" aria-live="polite">
            <div class="relative mx-auto mb-6 grid size-28 place-items-center" aria-hidden="true">
                <span class="absolute inset-0 animate-ping rounded-full bg-brand-soft opacity-60 [animation-iteration-count:1] [animation-duration:900ms]"></span>
                <span class="absolute inset-2 rounded-full bg-brand-soft"></span>
                <span class="relative grid size-16 place-items-center rounded-full bg-brand text-on-brand shadow-raised"><x-icon name="check" size="32" /></span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight"><span x-text="saved?.label"></span> is registered</h1>
            <p class="mx-auto mt-2 max-w-xs text-sm text-muted">Saved on this phone<span x-show="$store.outbox.pending"> and waiting to send</span><span x-show="! $store.outbox.pending">, and sent to the server</span>.</p>
            <p class="mt-5 inline-flex items-center gap-2 rounded-full bg-accent-soft px-4 py-2 text-base font-bold text-accent-fg">
                <x-icon name="star" size="18" /> +{{ $points['unverified'] }} points <span class="font-medium">· {{ $points['verified'] }} once verified</span>
            </p>
            <div class="mt-8 flex flex-col gap-3">
                <x-button type="button" size="xl" icon="plus" x-on:click="another()" class="w-full">Register another</x-button>
                <x-button :href="route('field.home')" variant="secondary" size="lg" class="w-full">Done</x-button>
            </div>
        </section>

        {{-- The form --}}
        <form x-show="! saved" x-ref="form" method="post" action="{{ route('field.register.store') }}" x-on:submit="submit($event)" novalidate class="space-y-7">
            @csrf
            <input type="hidden" name="uuid" value="{{ \Illuminate\Support\Str::uuid() }}">

            <div>
                <h1 class="text-2xl font-bold tracking-tight" x-text="fixing ? 'Fix this registration' : 'Register a voter'">Register a voter</h1>
                <p class="mt-1 text-sm text-muted" x-show="! fixing">Works without network. It sends when you’re back online.</p>
                <template x-if="fixing">
                    <x-alert tone="bad" class="mt-3"><span x-text="fixing.error"></span></x-alert>
                </template>
            </div>

            <section class="space-y-5">
                <div>
                    <label for="r-name" class="label">Full name</label>
                    <input id="r-name" name="name" class="input" autocomplete="off" autocapitalize="words" required :aria-invalid="!! errors.name">
                    <p class="field-error" x-show="errors.name" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.name?.[0]"></span></p>
                </div>
                <div>
                    <label for="r-phone" class="label">Phone number <span class="font-normal text-subtle">(if they have one)</span></label>
                    <div class="relative">
                        <x-icon name="phone" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-subtle" />
                        <input id="r-phone" name="phone" type="tel" inputmode="tel" class="input pl-10" placeholder="0803 123 4567" autocomplete="off" x-on:input="previewPhone($event.target.value)" :aria-invalid="!! errors.phone">
                    </div>
                    <p class="hint" x-show="phonePreview && ! errors.phone" x-text="phonePreview" :class="phonePreview.startsWith('Not') ? '!text-warn' : ''"></p>
                    <p class="field-error" x-show="errors.phone" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.phone?.[0]"></span></p>
                </div>
            </section>

            <div>
                <x-segmented name="gender" label="Gender" :options="config('canvass.genders')" :cols="2" />
                <p class="field-error" x-show="errors.gender" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.gender?.[0]"></span></p>
            </div>
            <div>
                <x-segmented name="age_band" label="Age" :options="config('canvass.age_bands')" :cols="3" />
                <p class="field-error" x-show="errors.age_band" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.age_band?.[0]"></span></p>
            </div>
            <div>
                <x-segmented name="occupation" label="Occupation" :options="config('canvass.occupations')" :cols="2" />
                <p class="field-error" x-show="errors.occupation" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.occupation?.[0]"></span></p>
            </div>

            <fieldset>
                <legend class="label">How do they feel about us?</legend>
                <div class="segmented" style="--cols: 1">
                    @foreach ($supportLevels as $key => $level)
                        <label class="!justify-start !px-4">
                            <input type="radio" name="support_level" value="{{ $key }}">
                            <span class="size-3 flex-none rounded-full" style="background: {{ $supportDots[$key] }}"></span>
                            {{ $level['label'] }}
                        </label>
                    @endforeach
                </div>
                <p class="field-error" x-show="errors.support_level" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.support_level?.[0]"></span></p>
            </fieldset>

            <div>
                <x-segmented name="top_issue" label="Their top issue" :options="config('canvass.issues')" :cols="2" />
            </div>

            <section class="card space-y-5 p-4">
                <p class="flex items-center gap-2 text-sm font-semibold"><x-icon name="map-pin" size="18" class="text-brand-fg" /> Where</p>
                <div>
                    <label for="r-ward" class="label">Ward</label>
                    <select id="r-ward" name="ward_id" class="input" x-model="wardId" required>
                        @foreach ($wards as $ward)
                            <option value="{{ $ward['id'] }}" @selected($ward['id'] === $defaultWard)>{{ $ward['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="field-error" x-show="errors.ward_id" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.ward_id?.[0]"></span></p>
                </div>
                <div>
                    <label for="r-pu" class="label">Polling unit <span class="font-normal text-subtle">(optional)</span></label>
                    <select id="r-pu" name="polling_unit_id" class="input">
                        <option value="">Not sure</option>
                        <template x-for="unit in wardUnits" :key="unit.id">
                            <option :value="unit.id" x-text="unit.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label for="r-community" class="label">Community or village <span class="font-normal text-subtle">(optional)</span></label>
                    <input id="r-community" name="community" class="input" autocomplete="off" autocapitalize="words">
                </div>
                <button type="button" class="btn btn-secondary btn-lg w-full" x-on:click="locate()" :disabled="locating">
                    <x-icon name="map-pinned" />
                    <span x-text="location ? 'Location added ✓' : (locating ? 'Finding location…' : 'Add location (optional)')">Add location (optional)</span>
                </button>
                <p class="hint !mt-2">Only used to check the ward. Never shown on a map.</p>
            </section>

            <div>
                <label for="r-notes" class="label">Notes <span class="font-normal text-subtle">(optional)</span></label>
                <textarea id="r-notes" name="notes" class="input" rows="3" maxlength="500" placeholder="Anything the coordinator should know"></textarea>
            </div>

            <section class="rounded-2xl border-[1.5px] p-4" :class="errors.consent ? 'border-bad bg-bad-soft' : 'border-line-strong bg-surface'">
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" name="consent" value="1" class="checkbox mt-0.5 size-6" required>
                    <span>
                        <span class="block text-base font-semibold">“{{ config('canvass.consent_text') }}”</span>
                        <span class="mt-1 block text-sm text-muted">Read this to the voter. Tick only if they say yes. <a href="{{ route('privacy') }}" class="link" target="_blank">Privacy notice</a></span>
                    </span>
                </label>
                <p class="field-error" x-show="errors.consent" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.consent?.[0]"></span></p>
            </section>

            <x-button size="xl" icon="check" class="w-full">Save voter</x-button>
        </form>
    </div>
</x-layouts.field>
