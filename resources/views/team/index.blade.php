<x-layouts.app title="Team & invites">
    <x-page-header title="Team & invites" eyebrow="Field"
        description="Invite agents by phone: they get a link, choose their own PIN and stay signed in on their phone for 30 days. If a phone is lost, sign that person out of every device.">
        <x-slot:actions>
            @if (auth()->user()->isAdmin())
                <x-button type="button" variant="secondary" icon="upload" x-data x-on:click="$dispatch('open-modal', 'import-agents')">Import agents</x-button>
            @endif
            <x-button type="button" icon="user-plus" x-data x-on:click="$dispatch('open-modal', 'invite')">Invite someone</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($imported)
        <section class="card mb-6 overflow-hidden">
            <div class="flex items-start gap-4 p-5">
                <span class="grid size-11 flex-none place-items-center rounded-2xl bg-good-soft text-good"><x-icon name="users" /></span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold">{{ number_format($imported['created']) }} field {{ \Illuminate\Support\Str::plural('agent', $imported['created']) }} enrolled</h2>
                    <p class="mt-1 text-sm text-muted">
                        They sign in with their phone number and the last 4 digits of it as their PIN.
                        {{ number_format($imported['placed']) }} are in their ward; the rest work in their LGA until their ward from the list matches a ward on the app (System → Ward names).
                    </p>
                </div>
            </div>
            @if ($imported['skippedTotal'] > 0)
                <details class="border-t border-line">
                    <summary class="cursor-pointer p-4 text-sm font-semibold text-warn">{{ number_format($imported['skippedTotal']) }} {{ \Illuminate\Support\Str::plural('row', $imported['skippedTotal']) }} not enrolled: show why</summary>
                    <x-table class="!rounded-none !border-0 !shadow-none">
                        <thead><tr><th class="n">Line</th><th>Name</th><th>Why</th></tr></thead>
                        <tbody>
                            @foreach ($imported['skipped'] as $row)
                                <tr><td class="n">{{ $row['line'] }}</td><td>{{ $row['name'] ?: '—' }}</td><td class="text-muted">{{ $row['reason'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </x-table>
                </details>
            @endif
        </section>
    @endif

    <form method="get" action="{{ route('team') }}" class="mb-4 flex gap-3">
        <x-input name="q" :value="$q" icon="search" placeholder="Name or phone number" aria-label="Search the team" class="flex-1" />
        <x-button variant="secondary" icon="search">Search</x-button>
    </form>

    @if ($invite)
        <section class="card mb-6 overflow-hidden" x-data="copy(@js($invite['message']))">
            <div class="flex items-start gap-4 bg-brand-softer p-5">
                <span class="grid size-11 flex-none place-items-center rounded-2xl bg-brand text-on-brand"><x-icon name="send" /></span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold">Send {{ $invite['name'] }} their link</h2>
                    <p class="mt-1 text-sm text-muted">The link works for 14 days and is shown only now. Send it straight to their phone.</p>
                </div>
            </div>
            <div class="space-y-4 p-5">
                <p class="rounded-xl border border-line bg-surface-2 p-4 text-sm break-words">{{ $invite['message'] }}</p>
                <div class="flex flex-wrap gap-2">
                    <x-button :href="'https://wa.me/'.ltrim($invite['phone'], '+').'?text='.rawurlencode($invite['message'])" target="_blank" rel="noopener" icon="message-square">WhatsApp</x-button>
                    <x-button :href="'sms:'.$invite['phone'].'?body='.rawurlencode($invite['message'])" variant="secondary" icon="smartphone">SMS</x-button>
                    <button type="button" class="btn btn-secondary" x-on:click="copy()"><x-icon name="copy" /><span x-text="copied ? 'Copied' : 'Copy message'">Copy message</span></button>
                </div>
            </div>
        </section>
    @endif

    <div class="card divide-y divide-line overflow-hidden">
        @forelse ($members as $member)
            <div class="flex flex-wrap items-center gap-x-4 gap-y-3 p-4 sm:p-5">
                <x-avatar :name="$member->name" :size="40" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $member->name }}</p>
                    <p class="truncate text-sm text-muted">{{ $member->role->label() }} · {{ $member->ward?->name ?? $member->lga?->name ?? '—' }}@if (! $member->ward_id && $member->ward_label) <span class="text-subtle">({{ $member->ward_label }}, not matched yet)</span>@endif · <span class="num">{{ \App\Support\Phone::local($member->phone) }}</span></p>
                </div>
                <div class="flex w-full flex-wrap items-center gap-2 pl-14 sm:w-auto sm:pl-0">
                    @if ($member->isDisabled())
                        <x-badge tone="bad" dot>Switched off</x-badge>
                    @elseif (! $member->invite_accepted_at && ! $member->last_login_at)
                        <x-badge tone="warn" dot>Invited</x-badge>
                    @elseif ($member->last_seen_at && $member->last_seen_at->gt(now()->subDays(14)))
                        <x-badge tone="good" dot>Active</x-badge>
                    @else
                        <x-badge dot>Dormant</x-badge>
                    @endif
                    <span class="num text-sm text-muted sm:w-28 sm:text-right"><span class="font-semibold text-ink">{{ number_format($registrations[$member->id] ?? 0) }}</span> registered</span>
                    @if ($member->role->rank() < auth()->user()->role->rank())
                        <x-dropdown width="w-60">
                            <x-slot:trigger>
                                <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="More for {{ $member->name }}"><x-icon name="ellipsis" /></button>
                            </x-slot:trigger>
                            <form method="post" action="{{ route('team.reinvite', $member) }}">@csrf<button class="menu-item"><x-icon name="send" />Send a new sign-in link</button></form>
                            <form method="post" action="{{ route('team.revoke', $member) }}" x-data x-on:submit="if (! confirm('Sign {{ addslashes($member->name) }} out of every device?')) $event.preventDefault()">@csrf<button class="menu-item"><x-icon name="log-out" />Sign out every device</button></form>
                        </x-dropdown>
                    @endif
                </div>
            </div>
        @empty
            @if ($q !== '')
                <x-empty icon="search" title="No one matches" description="Try another name or number." />
            @else
            <x-empty icon="users" title="No one in your team yet" description="Invite your first agent: all you need is their name and phone number.">
                <x-button type="button" icon="user-plus" x-data x-on:click="$dispatch('open-modal', 'invite')">Invite someone</x-button>
            </x-empty>
            @endif
        @endforelse
    </div>
    <div class="mt-4">{{ $members->links() }}</div>

    @if (auth()->user()->isAdmin())
        <x-modal name="import-agents" title="Import field agents">
            <form method="post" action="{{ route('team.import') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <p class="text-sm text-muted">A CSV with the columns <strong class="text-ink">name, phone, lga, ward</strong> (ward is optional). Each person signs in with their phone number, and the <strong class="text-ink">last 4 digits</strong> of it are their first PIN.</p>
                <input type="file" name="file" accept=".csv,text/csv" class="input" aria-label="Agents CSV" required>
                <x-checkbox name="must_change_pin" label="Make them choose their own PIN at first sign-in" description="Recommended: anyone who knows an agent’s number could guess the last 4 digits." :checked="true" />
                <p class="hint">Rows are skipped, never guessed: numbers that aren’t Nigerian mobiles, numbers already on the app, a number on two rows, missing names or unknown LGAs. You’ll see the list after the import.</p>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <x-button icon="upload">Import</x-button>
                </div>
            </form>
        </x-modal>
    @endif

    <x-modal name="invite" title="Invite someone">
        <form method="post" action="{{ route('team.store') }}" class="space-y-5">
            @csrf
            <x-input name="name" label="Full name" required autocomplete="off" />
            <x-input name="phone" type="tel" inputmode="tel" label="Phone number" placeholder="0803 123 4567" required hint="They sign in with this number." />
            @if (count($roles) > 1)
                <x-segmented name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" value="agent" :cols="2" />
            @else
                <input type="hidden" name="role" value="agent">
            @endif
            <x-select name="ward_id" label="Ward" :options="$wards" :value="auth()->user()->ward_id" placeholder="Choose the ward" required />
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="send">Create invite link</x-button>
            </div>
        </form>
    </x-modal>
    @if ($errors->any())
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'invite'))"></div>
    @endif
</x-layouts.app>
