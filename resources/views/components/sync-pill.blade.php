{{-- The field sync pill: "All synced ✓", "12 waiting", "Offline: 12 saved on this phone", "2 failed: tap to fix". --}}
<a href="{{ route('field.outbox') }}" x-data x-show="$store.outbox.ready" x-cloak
    class="badge h-8 max-w-[62vw] px-3 text-[13px] transition-colors"
    :class="{ 'badge-good': $store.outbox.tone === 'good', 'badge-info': $store.outbox.tone === 'info', 'badge-warn': $store.outbox.tone === 'warn', 'badge-bad': $store.outbox.tone === 'bad' }"
    aria-live="polite">
    <x-icon name="cloud-check" x-show="$store.outbox.tone === 'good'" />
    <x-icon name="refresh-cw" x-show="$store.outbox.tone === 'info'" x-bind:class="$store.outbox.syncing && 'animate-spin'" />
    <x-icon name="cloud-off" x-show="$store.outbox.tone === 'warn'" />
    <x-icon name="circle-alert" x-show="$store.outbox.tone === 'bad'" />
    <span class="truncate" x-text="$store.outbox.label">All synced</span>
    <span x-show="$store.outbox.tone === 'good'" aria-hidden="true">✓</span>
</a>
