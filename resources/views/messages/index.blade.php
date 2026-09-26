@php
    $tab = fn (?string $key, string $label) => [$label, route('messages', array_filter(['status' => $key])), $status === $key, $key ? ($counts[$key] ?? 0) : $counts->sum()];
@endphp
<x-layouts.app title="Messages">
    <x-page-header title="Messages" eyebrow="Engage" description="Claude drafts three versions of a message for a segment, from the campaign’s policy brief and the field’s top issues. A person edits and approves one; nothing is sent from here.">
        <x-slot:actions>
            <x-button :href="route('policies')" variant="secondary" icon="book-open">Policy brief</x-button>
            <x-button :href="route('messages.create')" icon="sparkles">New message</x-button>
        </x-slot:actions>
    </x-page-header>

    @unless ($configured)
        <x-alert tone="warn" title="AI drafting isn’t set up yet" class="mb-6">
            Add the Claude API key on the System page (or <code>ANTHROPIC_API_KEY</code> in .env). Until then you can still write the policy brief.
            @if (auth()->user()->role->value === 'admin')
                <x-slot:actions><x-button :href="route('system').'#connections'" size="sm" variant="secondary" icon="key-round">Open System</x-button></x-slot:actions>
            @endif
        </x-alert>
    @endunless

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi label="Waiting for review" :value="$counts['ready'] ?? 0" icon="pencil" :href="route('messages', ['status' => 'ready'])" />
        <x-kpi label="Approved messages" :value="$counts['approved'] ?? 0" icon="badge-check" :href="route('messages', ['status' => 'approved'])" />
        <div class="card flex min-w-0 flex-col gap-3 p-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-medium text-muted">AI spend this month</p>
                <span class="grid size-8 place-items-center rounded-lg bg-surface-2 text-muted"><x-icon name="gauge" size="17" /></span>
            </div>
            <p class="num text-3xl font-bold tracking-tight">${{ number_format($spend, 2) }}</p>
            @if ($budget > 0)
                <x-progress :value="min($spend, $budget)" :max="$budget" :tone="$spend >= $budget * 0.8 ? 'warn' : 'brand'" label="Budget used" />
                <p class="text-xs text-subtle">of ${{ number_format($budget, 0) }} budget · {{ $policies }} {{ Str::plural('policy brief', $policies) }} in use</p>
            @else
                <p class="text-xs text-subtle">No monthly limit · {{ $policies }} {{ Str::plural('policy brief', $policies) }} in use</p>
            @endif
        </div>
    </div>

    <x-tabs :items="[$tab(null, 'All'), $tab('ready', 'To review'), $tab('approved', 'Approved'), $tab('failed', 'Failed'), $tab('rejected', 'Rejected')]" />

    <div class="grid gap-3">
        @forelse ($drafts as $draft)
            <a href="{{ route('messages.show', $draft) }}" class="card card-interactive flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:gap-5">
                <span class="grid size-11 flex-none place-items-center rounded-xl bg-brand-soft text-brand-fg"><x-icon :name="['sms' => 'message-square', 'whatsapp' => 'send', 'radio' => 'radio', 'town_hall' => 'users', 'flyer' => 'file-text'][$draft->channel] ?? 'sparkles'" /></span>
                <span class="min-w-0 flex-1">
                    <span class="line-clamp-2 block font-semibold">{{ $draft->goal }}</span>
                    <span class="mt-1 block truncate text-sm text-muted">{{ $draft->audience }} · {{ number_format($draft->audience_size) }} canvassed</span>
                    <span class="mt-1 block text-xs text-subtle">{{ $draft->author?->name ?? 'Unknown' }} · {{ $draft->created_at->diffForHumans() }}@if ($draft->approver) · approved by {{ $draft->approver->name }}@endif</span>
                </span>
                <span class="flex flex-wrap items-center gap-1.5 sm:flex-col sm:items-end">
                    <x-badge :tone="$draft->statusTone()" dot>{{ $draft->statusLabel() }}</x-badge>
                    <span class="flex gap-1.5"><x-badge>{{ $draft->channelLabel() }}</x-badge><x-badge>{{ $draft->languageLabel() }}</x-badge></span>
                </span>
            </a>
        @empty
            <div class="card"><x-empty icon="sparkles" title="No messages yet" description="Pick a segment on the Segments page and choose “Draft a message”, or start a new message here." /></div>
        @endforelse
    </div>
    @if ($drafts->hasPages())<div class="mt-4">{{ $drafts->links() }}</div>@endif
</x-layouts.app>
