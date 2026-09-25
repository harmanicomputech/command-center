<x-layouts.field title="On this phone" :back="route('field.home')">
    <div x-data="outboxList()">
        <div class="card p-5">
            <div class="flex items-start gap-4">
                <span class="grid size-12 flex-none place-items-center rounded-2xl" :class="$store.outbox.total ? 'bg-info-soft text-info' : 'bg-good-soft text-good'">
                    <x-icon name="cloud-upload" x-show="$store.outbox.total" size="22" />
                    <x-icon name="cloud-check" x-show="! $store.outbox.total" size="22" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-semibold" x-text="$store.outbox.total ? $store.outbox.label : 'Everything is on the server'">Everything is on the server</p>
                    <p class="mt-1 text-sm text-muted">Work is saved here first and removed only after the server confirms it. Closing the app is safe.</p>
                </div>
            </div>
            <x-button type="button" size="lg" icon="refresh-cw" class="mt-5 w-full" x-on:click="$store.outbox.sync(true)" x-bind:disabled="$store.outbox.syncing || ! $store.outbox.pending">
                <span x-text="$store.outbox.syncing ? 'Sending…' : 'Sync now'">Sync now</span>
            </x-button>
        </div>

        <h2 class="mt-6 mb-3 text-base font-semibold" x-show="items.length" x-cloak>Waiting to send</h2>
        <ul class="card divide-y divide-line" x-show="items.length" x-cloak>
            <template x-for="item in items" :key="item.id">
                <li class="p-4">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 size-2.5 flex-none rounded-full" :class="item.status === 'failed' ? 'bg-bad' : 'bg-info'"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold" x-text="item.label"></p>
                            <p class="text-xs text-subtle"><span x-text="item.type === 'voter' ? 'Registration' : item.type"></span> · <span x-text="when(item)"></span></p>
                            <p class="mt-1 text-sm" :class="item.status === 'failed' ? 'text-bad font-medium' : 'text-muted'" x-show="item.error" x-text="item.error"></p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2 pl-5" x-show="item.status === 'failed'">
                        <a :href="'{{ route('field.register') }}?fix=' + item.id" class="btn btn-primary btn-sm" x-show="item.type === 'voter'"><x-icon name="pencil" />Fix</a>
                        <button type="button" class="btn btn-ghost btn-sm" x-on:click="discard(item)"><x-icon name="trash-2" />Delete</button>
                    </div>
                </li>
            </template>
        </ul>

        <div class="mt-6 card" x-show="! items.length">
            <x-empty icon="cloud-check" title="Nothing waiting" description="Everything you saved has reached the server." compact>
                <x-button :href="route('field.registrations')" variant="secondary" size="sm">My registrations</x-button>
            </x-empty>
        </div>
    </div>
</x-layouts.field>
