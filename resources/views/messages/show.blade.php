@php
    $isSms = $draft->channel === 'sms';
    $initialIndex = $draft->chosen ?? 0;
    $initialText = $draft->final_text ?? ($variants[$initialIndex]['text'] ?? '');
@endphp
<x-layouts.app title="Message draft">
    <x-page-header :title="$draft->statusLabel() === 'Approved' ? 'Approved message' : 'Message draft'" eyebrow="Messages" :back="route('messages')" :description="$draft->goal">
        <x-slot:actions>
            @if (in_array($draft->status, ['ready', 'failed', 'approved', 'rejected'], true))
                <form method="post" action="{{ route('messages.again', $draft) }}">@csrf<x-button variant="secondary" icon="refresh-cw">Draft again</x-button></form>
            @endif
            @if (in_array($draft->status, ['ready', 'failed'], true))
                <form method="post" action="{{ route('messages.reject', $draft) }}">@csrf<x-button variant="ghost" icon="x">Reject</x-button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 space-y-6">
            @if ($draft->status === 'queued')
                <section class="card flex flex-col items-center px-6 py-14 text-center" x-data="waitFor(@js(route('messages.status', $draft)))">
                    <span class="relative mb-5 grid size-16 place-items-center rounded-2xl bg-brand-soft text-brand-fg">
                        <x-icon name="sparkles" size="28" class="animate-pulse motion-reduce:animate-none" />
                    </span>
                    <h2 class="text-lg font-semibold">Claude is drafting three versions…</h2>
                    <p class="mt-1 max-w-sm text-sm text-muted">This usually takes under a minute. You can leave this page; the draft will be here when it’s done.</p>
                    <p class="num mt-4 text-xs text-subtle" x-text="seconds + 's'" aria-live="off"></p>
                    <div class="mt-6 grid w-full max-w-2xl gap-3 sm:grid-cols-3" aria-hidden="true">
                        @foreach (range(1, 3) as $i)
                            <div class="rounded-xl border border-line p-4 text-left"><x-skeleton :lines="3" /></div>
                        @endforeach
                    </div>
                </section>
            @elseif ($draft->status === 'failed')
                <x-alert tone="bad" title="The draft didn’t work">{{ $draft->error ?? 'Something went wrong.' }} Use “Draft again” to retry.</x-alert>
            @endif

            @if ($variants->isNotEmpty())
                <div x-data="{ chosen: {{ (int) $initialIndex }}, text: @js($initialText), variants: @js($variants->pluck('text')), get sms() { return window.cc.sms(this.text) } }" class="space-y-6">
                    <section>
                        <h2 class="mb-3 text-base font-semibold">Three versions</h2>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            @foreach ($variants as $i => $variant)
                                <article class="card flex flex-col p-5 transition-shadow" :class="chosen === {{ $i }} && 'ring-2 ring-brand'">
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <span class="eyebrow">Version {{ $i + 1 }}</span>
                                        @if ($draft->chosen === $i && $draft->status === 'approved')<x-badge tone="good" icon="check">Approved</x-badge>@endif
                                    </div>
                                    @if ($variant['angle'])<p class="mb-2 text-xs font-medium text-brand-fg">{{ $variant['angle'] }}</p>@endif
                                    <p class="flex-1 text-sm whitespace-pre-line">{{ $variant['text'] }}</p>
                                    <p class="num mt-3 text-xs {{ $isSms && $variant['sms']['parts'] > 1 ? 'text-warn' : 'text-subtle' }}">
                                        {{ $variant['sms']['length'] }} characters{{ $isSms ? ' · '.$variant['sms']['parts'].' SMS'.($variant['sms']['unicode'] ? ' (Unicode)' : '') : '' }}
                                    </p>
                                    @if ($draft->status !== 'rejected')
                                        <x-button type="button" variant="secondary" size="sm" class="mt-4" icon="pencil" x-on:click="chosen = {{ $i }}; text = variants[{{ $i }}]; $nextTick(() => $refs.editor.focus())">Use this one</x-button>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>

                    @if ($draft->status !== 'rejected')
                        <form method="post" action="{{ route('messages.approve', $draft) }}" class="card p-5 sm:p-6">
                            @csrf
                            <input type="hidden" name="chosen" :value="chosen">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h2 class="text-base font-semibold">Edit and approve <span class="font-normal text-muted" x-text="'(version ' + (chosen + 1) + ')'"></span></h2>
                                <p class="num text-xs" :class="{{ $isSms ? 'sms.parts > 1' : 'false' }} ? 'text-warn' : 'text-subtle'">
                                    <span x-text="sms.length"></span> / {{ $draft->limit() }} characters
                                    @if ($isSms)
                                        · <span x-text="sms.parts"></span> SMS<span x-show="sms.unicode"> (Unicode)</span>
                                    @endif
                                </p>
                            </div>
                            <label for="final-text" class="sr-only">Approved text</label>
                            <textarea id="final-text" name="final_text" x-ref="editor" x-model="text" rows="6" class="input" required></textarea>
                            @error('final_text')<p class="field-error"><x-icon name="circle-alert" />{{ $message }}</p>@enderror
                            <p class="hint">Check facts and names before approving. Your name is saved with the approval.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <x-button icon="badge-check">{{ $draft->status === 'approved' ? 'Save changes' : 'Approve' }}</x-button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            @if ($draft->status === 'approved')
                <section class="card overflow-hidden">
                    <div class="flex items-start gap-4 bg-good-soft p-5">
                        <span class="grid size-10 flex-none place-items-center rounded-full bg-good text-on-brand"><x-icon name="badge-check" /></span>
                        <div class="min-w-0">
                            <p class="font-semibold">Approved by {{ $draft->approver?->name ?? 'someone' }}</p>
                            <p class="text-sm text-muted">{{ \App\Support\Time::local($draft->approved_at, 'j M Y, g:i a') }}</p>
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="text-sm whitespace-pre-line">{{ $draft->final_text }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span x-data="copy(@js($draft->final_text))"><x-button type="button" variant="secondary" size="sm" icon="copy" x-on:click="copy()"><span x-text="copied ? 'Copied' : 'Copy'">Copy</span></x-button></span>
                            @if ($canBroadcast)
                                <x-button :href="route('broadcasts.create', ['draft' => $draft->id])" size="sm" icon="megaphone">Send as SMS broadcast</x-button>
                            @endif
                        </div>
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <x-card title="Brief" icon="clipboard-list">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-subtle">Audience</dt><dd class="font-medium">{{ $draft->audience }}</dd><dd class="num text-xs text-muted">{{ number_format($draft->audience_size) }} canvassed</dd></div>
                    <div class="flex gap-6">
                        <div><dt class="text-subtle">Channel</dt><dd class="font-medium">{{ $draft->channelLabel() }}</dd></div>
                        <div><dt class="text-subtle">Language</dt><dd class="font-medium">{{ $draft->languageLabel() }}</dd></div>
                    </div>
                    @if ($draft->tone)<div><dt class="text-subtle">Tone</dt><dd class="font-medium">{{ config('messaging.tones.'.$draft->tone) }}</dd></div>@endif
                    <div><dt class="text-subtle">Requested by</dt><dd class="font-medium">{{ $draft->author?->name ?? 'Unknown' }}</dd><dd class="text-xs text-muted">{{ $draft->created_at->diffForHumans() }}</dd></div>
                </dl>
            </x-card>
            @if ($draft->aiCall)
                <x-card title="AI usage" icon="gauge">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-subtle">Cost</dt><dd class="num font-semibold">${{ number_format($draft->aiCall->cost_usd, 4) }}</dd></div>
                        <div><dt class="text-subtle">Time</dt><dd class="num font-semibold">{{ number_format(($draft->aiCall->duration_ms ?? 0) / 1000, 1) }}s</dd></div>
                        <div><dt class="text-subtle">Tokens in / out</dt><dd class="num">{{ number_format($draft->aiCall->input_tokens + $draft->aiCall->cache_read_tokens + $draft->aiCall->cache_write_tokens) }} / {{ number_format($draft->aiCall->output_tokens) }}</dd></div>
                        <div><dt class="text-subtle">From cache</dt><dd class="num">{{ number_format($draft->aiCall->cache_read_tokens) }}</dd></div>
                    </dl>
                    <p class="mt-3 truncate text-xs text-subtle">{{ $draft->aiCall->model }}</p>
                </x-card>
            @endif
        </aside>
    </div>
</x-layouts.app>
