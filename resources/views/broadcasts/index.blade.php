<x-layouts.app title="Broadcasts">
    <x-page-header title="SMS broadcasts" eyebrow="Engage" description="Messages to consenting voters in a segment, or to the team, through Africa’s Talking. Delivery reports and STOP replies come back automatically.">
        <x-slot:actions>
            <x-button :href="route('broadcasts.create')" icon="plus">New broadcast</x-button>
        </x-slot:actions>
    </x-page-header>

    @unless ($configured)
        <x-alert tone="warn" title="SMS isn’t set up yet" class="mb-6">Add the Africa’s Talking username and API key on the System page to send. You can prepare drafts now.</x-alert>
    @endunless

    <div class="grid gap-3">
        @forelse ($broadcasts as $broadcast)
            @php
                $c = $counts[$broadcast->id] ?? collect();
                $delivered = (int) ($c['delivered'] ?? 0);
                $done = $delivered + (int) ($c['sent'] ?? 0) + (int) ($c['failed'] ?? 0);
            @endphp
            <a href="{{ route('broadcasts.show', $broadcast) }}" class="card card-interactive flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <span class="grid size-11 flex-none place-items-center rounded-xl bg-brand-soft text-brand-fg"><x-icon name="megaphone" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold">{{ $broadcast->title }}</span>
                    <span class="mt-0.5 block truncate text-sm text-muted">{{ $broadcast->audience_label }}</span>
                    <span class="mt-1 block text-xs text-subtle">{{ ($broadcast->sender ?? $broadcast->author)?->name ?? 'Unknown' }} · {{ ($broadcast->started_at ?? $broadcast->created_at)->diffForHumans() }}</span>
                </span>
                <span class="flex items-center gap-4 sm:w-64 sm:flex-none">
                    @if ($broadcast->status === 'draft')
                        <span class="num flex-1 text-sm text-muted">{{ number_format($broadcast->recipients) }} people</span>
                    @else
                        <span class="min-w-0 flex-1">
                            <span class="num mb-1 flex justify-between text-xs text-muted"><span>{{ number_format($delivered) }} delivered</span><span>{{ number_format($broadcast->recipients) }}</span></span>
                            <x-progress :value="$delivered" :max="max(1, $broadcast->recipients)" tone="good" label="Delivered" />
                        </span>
                    @endif
                    <x-badge :tone="$broadcast->statusTone()" dot>{{ $broadcast->statusLabel() }}</x-badge>
                </span>
            </a>
        @empty
            <div class="card"><x-empty icon="megaphone" title="No broadcasts yet" description="Approve an SMS message on the Messages page, or start a broadcast here." /></div>
        @endforelse
    </div>
    @if ($broadcasts->hasPages())<div class="mt-4">{{ $broadcasts->links() }}</div>@endif
</x-layouts.app>
