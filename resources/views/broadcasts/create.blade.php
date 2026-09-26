@php
    $initialMessage = old('message', $draft?->final_text ?? '');
    $roles = collect(\App\Services\Broadcasting\Audience::TEAM_ROLES)->mapWithKeys(fn ($role) => [$role => \App\Enums\UserRole::from($role)->label()]);
@endphp
<x-layouts.app title="New broadcast">
    <x-page-header title="New SMS broadcast" eyebrow="Broadcasts" :back="route('broadcasts')" description="To consenting voters in a segment, or to the team. Numbers that replied STOP are always left out, and each number gets one message." />

    @unless ($configured)
        <x-alert tone="warn" title="SMS isn’t set up yet" class="mb-6">You can prepare a broadcast now; sending needs the Africa’s Talking username and API key on the System page.</x-alert>
    @endunless

    <form id="pick-segment" method="get" action="{{ route('broadcasts.create') }}">@if ($draft)<input type="hidden" name="draft" value="{{ $draft->id }}">@endif</form>

    <form method="post" action="{{ route('broadcasts.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]"
        x-data="broadcastForm({
            url: @js(route('broadcasts.preview')),
            message: @js($initialMessage),
            footer: @js($footer),
            filters: @js((object) $audience['filters']),
            count: {{ $count }},
            costPerPart: {{ $costPerPart }},
        })">
        @csrf
        @if ($draft)<input type="hidden" name="draft_id" value="{{ $draft->id }}">@endif
        <input type="hidden" name="audience[type]" :value="type">
        @foreach ($audience['filters'] as $key => $values)
            @foreach ($values as $value)<input type="hidden" name="audience[filters][{{ $key }}][]" value="{{ $value }}">@endforeach
        @endforeach

        <div class="min-w-0 space-y-6">
            @if ($draft)
                <x-alert tone="good" title="From an approved message" icon="badge-check">Approved by {{ $draft->approver?->name ?? 'someone' }}. Any change here is saved with the broadcast.</x-alert>
            @endif

            <x-card title="Who gets it" icon="users">
                <div class="segmented mb-5" style="--cols: 2">
                    <label><input type="radio" value="voters" x-model="type"> Voters in a segment</label>
                    <label><input type="radio" value="team" x-model="type"> Our team</label>
                </div>

                <div x-show="type === 'voters'">
                    <p class="font-medium">{{ $audienceLabel }}</p>
                    <p class="mt-1 text-sm text-muted">Only voters with a number, consent on record and no opt-out.</p>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        @if ($saved->isNotEmpty())
                            <div class="min-w-0 flex-1">
                                <label for="b-segment" class="label">Saved segment</label>
                                <select id="b-segment" name="segment" form="pick-segment" class="input" onchange="this.form.submit()">
                                    <option value="">Choose…</option>
                                    @foreach ($saved as $option)<option value="{{ $option->id }}" @selected((int) request('segment') === $option->id)>{{ $option->name }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                        <x-button :href="route('segments', $audience['filters'])" variant="secondary" icon="funnel">Build on Segments</x-button>
                    </div>
                </div>

                <div x-show="type === 'team'" x-cloak class="space-y-5">
                    <fieldset>
                        <legend class="eyebrow mb-2">Roles</legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($roles as $value => $roleLabel)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="audience[roles][]" value="{{ $value }}" x-model="roles" class="peer sr-only">
                                    <span class="inline-flex h-9 items-center rounded-full border border-line-strong px-3.5 text-[13px] font-medium transition-colors peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand-fg peer-focus-visible:outline-2 peer-focus-visible:outline-brand hover:bg-surface-2">{{ $roleLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="eyebrow mb-2">LGAs <span class="font-normal normal-case">(none ticked: all)</span></legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($lgas as $lga)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="audience[lga_id][]" value="{{ $lga->id }}" x-model="lgas" class="peer sr-only">
                                    <span class="inline-flex h-9 items-center rounded-full border border-line-strong px-3.5 text-[13px] font-medium transition-colors peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand-fg peer-focus-visible:outline-2 peer-focus-visible:outline-brand hover:bg-surface-2">{{ $lga->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </x-card>

            <x-card title="Message" icon="message-square">
                <x-input name="title" label="Name (for the log, not sent)" :value="old('title', $draft ? Str::limit($draft->goal, 80, '') : '')" placeholder="e.g. Izzi farmers, town hall invite" required />
                <div class="mt-5">
                    <div class="mb-1.5 flex items-end justify-between gap-2">
                        <label for="b-message" class="label mb-0">Text</label>
                        <span class="num text-xs" :class="sms.parts > 1 ? 'text-warn' : 'text-subtle'"><span x-text="sms.length"></span> characters · <span x-text="sms.parts"></span> SMS<span x-show="sms.unicode"> (Unicode)</span></span>
                    </div>
                    <textarea id="b-message" name="message" x-model="message" rows="5" class="input" maxlength="918" required></textarea>
                    @error('message')<p class="field-error"><x-icon name="circle-alert" />{{ $message }}</p>@enderror
                    <p class="hint" x-show="footer">“<span x-text="footer"></span>” is added at the end (it counts towards the length).</p>
                    <p class="hint text-warn" x-show="sms.unicode" x-cloak>Letters outside the basic SMS alphabet (such as ị, ọ, ụ or emoji) make every part hold 70 characters instead of 160.</p>
                </div>
            </x-card>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <section class="card p-5">
                <p class="eyebrow mb-3">Preview</p>
                <div class="rounded-2xl bg-surface-2 p-4">
                    <div class="max-w-[260px] rounded-2xl rounded-bl-md bg-surface p-3 text-sm whitespace-pre-line shadow-xs" x-text="full || 'Your message appears here.'" :class="! message && 'text-subtle'"></div>
                </div>
                <dl class="mt-5 grid grid-cols-3 gap-3 text-center">
                    <div><dt class="text-xs text-muted">People</dt><dd class="num text-xl font-semibold" x-text="count.toLocaleString()">{{ number_format($count) }}</dd></div>
                    <div><dt class="text-xs text-muted">SMS each</dt><dd class="num text-xl font-semibold" x-text="sms.parts"></dd></div>
                    <div><dt class="text-xs text-muted">Est. cost</dt><dd class="num text-xl font-semibold" x-text="'₦' + cost.toLocaleString()"></dd></div>
                </dl>
                <p class="mt-3 text-center text-xs text-subtle">At ₦{{ number_format($costPerPart, 2) }} per SMS part (Settings). <span x-show="loading">Updating…</span></p>
            </section>
            <x-button size="lg" icon="check" class="w-full">Save as draft</x-button>
            <p class="text-center text-xs text-subtle">Nothing is sent yet: you confirm on the next page.</p>
        </aside>
    </form>
</x-layouts.app>
