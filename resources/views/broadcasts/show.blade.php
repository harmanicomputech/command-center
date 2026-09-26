@php
    $delivered = (int) ($counts['delivered'] ?? 0);
    $sent = (int) ($counts['sent'] ?? 0);
    $failed = (int) ($counts['failed'] ?? 0);
    $queued = (int) ($counts['queued'] ?? 0);
@endphp
<x-layouts.app title="Broadcast">
    <x-page-header :title="$broadcast->title" eyebrow="Broadcasts" :back="route('broadcasts')" :description="$broadcast->audience_label">
        <x-slot:actions>
            <x-badge :tone="$broadcast->statusTone()" dot>{{ $broadcast->statusLabel() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-6">
            @if ($broadcast->status !== 'draft')
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <x-kpi label="Recipients" :value="$broadcast->recipients" icon="users" />
                    <x-kpi label="Delivered" :value="$delivered" icon="circle-check" />
                    <x-kpi label="On the way" :value="$sent + $queued" icon="send" />
                    <x-kpi label="Failed" :value="$failed" icon="circle-alert" />
                </div>
                @if ($failures->isNotEmpty())
                    <x-card title="Why messages failed" icon="circle-alert">
                        @foreach ($failures as $reason => $n)
                            <div class="flex justify-between gap-3 border-b border-line py-2 text-sm last:border-0"><span class="min-w-0 truncate">{{ $reason ?: 'Unknown' }}</span><span class="num font-semibold">{{ number_format($n) }}</span></div>
                        @endforeach
                    </x-card>
                @endif
            @endif

            <x-card title="Message" icon="message-square">
                <div class="rounded-2xl bg-surface-2 p-4">
                    <div class="max-w-sm rounded-2xl rounded-bl-md bg-surface p-3 text-sm whitespace-pre-line shadow-xs">{{ $broadcast->message }}</div>
                </div>
                <p class="num mt-3 text-xs text-subtle">{{ $sms['length'] }} characters · {{ $sms['parts'] }} SMS{{ $sms['unicode'] ? ' (Unicode)' : '' }}</p>
                @if ($broadcast->draft)
                    <p class="mt-3 text-sm text-muted">From an AI draft approved by {{ $broadcast->draft->approver?->name ?? 'someone' }}. <a href="{{ route('messages.show', $broadcast->draft) }}" class="font-medium text-brand-fg underline">Open the draft</a></p>
                @endif
            </x-card>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            @if ($broadcast->status === 'draft')
                <section class="card p-5">
                    <p class="eyebrow mb-3">Ready to send</p>
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div><dt class="text-xs text-muted">People</dt><dd class="num text-xl font-semibold">{{ number_format($recipients) }}</dd></div>
                        <div><dt class="text-xs text-muted">SMS each</dt><dd class="num text-xl font-semibold">{{ $broadcast->parts }}</dd></div>
                        <div><dt class="text-xs text-muted">Est. cost</dt><dd class="num text-xl font-semibold">₦{{ number_format($cost) }}</dd></div>
                    </dl>
                    <form method="post" action="{{ route('broadcasts.send', $broadcast) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-checkbox name="confirm" :label="'Send to '.number_format($recipients).' people now'" description="SMS can’t be recalled once sent." />
                        <x-button size="lg" icon="send" class="w-full" :disabled="! $configured || $recipients === 0">Send broadcast</x-button>
                        @unless ($configured)<p class="text-center text-xs text-warn">Set up Africa’s Talking on the System page first.</p>@endunless
                    </form>
                </section>
                <form method="post" action="{{ route('broadcasts.destroy', $broadcast) }}" onsubmit="return confirm('Delete this draft?')">
                    @csrf @method('delete')
                    <x-button variant="ghost" icon="trash-2" class="w-full">Delete draft</x-button>
                </form>
            @else
                <x-card title="Sending" icon="clock">
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-subtle">Sent by</dt><dd class="font-medium">{{ $broadcast->sender?->name ?? 'Unknown' }}</dd></div>
                        <div><dt class="text-subtle">Started</dt><dd class="font-medium">{{ \App\Support\Time::local($broadcast->started_at, 'j M Y, g:i a') }}</dd></div>
                        @if ($broadcast->finished_at)<div><dt class="text-subtle">All handed to the network</dt><dd class="font-medium">{{ \App\Support\Time::local($broadcast->finished_at, 'j M Y, g:i a') }}</dd></div>@endif
                    </dl>
                </x-card>
            @endif
        </aside>
    </div>
</x-layouts.app>
