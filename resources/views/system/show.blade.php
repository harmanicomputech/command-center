<x-layouts.app title="System">
    <x-page-header title="System" eyebrow="Admin" description="Everything that would need a terminal on a normal server: the database, the register and the background work." />

    <div class="space-y-6">
        @unless ($register['confirmed'])
            <x-alert tone="warn" title="Check the register before relying on it" id="register-warning">
                The loaded register totals <strong class="num">{{ number_format($register['registered']) }}</strong> registered voters, about
                <strong>{{ $register['ratio'] }}×</strong> INEC’s 2023 figure for Ebonyi (about 1.6 million), and many polling unit names look generic
                (“Open Space 001”). Every priority-zone figure uses these numbers. Upload INEC’s register below, or confirm these figures if the campaign has checked them.
            </x-alert>
        @endunless

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Update database" description="After uploading a new version, run this once." icon="database">
                @if ($pendingMigrations)
                    <x-alert tone="info" class="mb-4">{{ count($pendingMigrations) }} {{ \Illuminate\Support\Str::plural('update', count($pendingMigrations)) }} waiting.</x-alert>
                @else
                    <p class="mb-4 flex items-center gap-2 text-sm font-medium text-good"><x-icon name="circle-check" size="18" /> The database is up to date.</p>
                @endif
                <form method="post" action="{{ route('system.migrate') }}">
                    @csrf
                    <x-button :variant="$pendingMigrations ? 'primary' : 'secondary'" icon="refresh-cw">Update database</x-button>
                </form>
            </x-card>

            <x-card title="Background work" description="Sends messages, fetches news and prepares the daily brief. The host has no per-minute cron, so a free pinger drives it." icon="activity" id="runner">
                <div class="mb-4 flex items-center gap-3">
                    @if ($runnerHealthy)
                        <x-badge tone="good" dot>✓ Running</x-badge>
                        <span class="text-sm text-muted">Last run {{ $heartbeat->diffForHumans() }}{{ $runnerSource ? ' ('.$runnerSource.')' : '' }}</span>
                    @elseif ($heartbeat)
                        <x-badge tone="warn" dot>Stalled</x-badge>
                        <span class="text-sm text-muted">Last run {{ $heartbeat->diffForHumans() }}</span>
                    @else
                        <x-badge tone="bad" dot>Not running</x-badge>
                        <span class="text-sm text-muted">No run yet.</span>
                    @endif
                </div>
                <p class="label">Pinger URL</p>
                <div x-data="copy(@js($runnerUrl))" class="flex gap-2">
                    <input type="text" readonly value="{{ $runnerUrl }}" class="input num min-w-0 flex-1 text-xs" aria-label="Pinger URL" x-on:focus="$el.select()">
                    <button type="button" class="btn btn-secondary flex-none" x-on:click="copy()"><x-icon name="copy" /><span x-text="copied ? 'Copied' : 'Copy'">Copy</span></button>
                </div>
                <p class="hint">At <a href="https://cron-job.org" class="link" target="_blank" rel="noopener">cron-job.org</a> (free), create a job that opens this URL every minute. Keep it private.
                    {{ $afterResponse ? 'Page visits also run it.' : '' }} Queue: {{ $queue['waiting'] }} waiting, {{ $queue['failed'] }} failed.</p>
            </x-card>
        </div>

        <x-card title="Polling unit register" id="register" icon="map-pin"
            description="LGAs, wards and polling units. Every voter, task and issue belongs to a ward from here.">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-stat label="Polling units" :value="number_format($register['units'])" />
                <x-stat label="Wards" :value="number_format($register['wards'])" />
                <x-stat label="LGAs" :value="number_format($register['lgas'])" />
                <x-stat label="Registered voters" :value="number_format($register['registered'])" />
            </div>
            <p class="mt-4 text-sm text-muted">
                {{ number_format($register['units']) }} polling units in {{ $register['lgas'] }} LGAs and {{ $register['wards'] }} wards, from {{ $register['source'] }}.
                @if ($register['confirmed'])
                    <span class="font-medium text-good">Confirmed by {{ $register['confirmed_by'] }} on {{ \App\Support\Time::local(\App\Support\Time::parse($register['confirmed_at']), 'j M Y') }}.</span>
                @endif
            </p>

            <div class="mt-6 grid grid-cols-1 gap-6 border-t border-line pt-6 lg:grid-cols-2">
                <form method="post" action="{{ route('system.register') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <p class="text-sm font-semibold">Replace or update from a CSV</p>
                    <input type="file" name="file" accept=".csv,text/csv" class="input" aria-label="Register CSV">
                    <p class="hint !mt-0">Columns: code, name, ward, lga, registered_voters. Rows are matched by PU code, so nothing is duplicated. Leave empty to reload the bundled file.</p>
                    <x-checkbox name="official" label="This is INEC’s official register" description="Confirms the figures and removes the warning." />
                    <x-button variant="secondary" icon="upload">Import register</x-button>
                </form>
                @unless ($register['confirmed'])
                    <form method="post" action="{{ route('system.register.confirm') }}" class="space-y-4">
                        @csrf
                        <p class="text-sm font-semibold">Or confirm the figures as they are</p>
                        <x-checkbox name="confirm" label="We have checked these figures" description="The campaign accepts the risk of using them for priority zones." />
                        <x-button variant="secondary" icon="badge-check">Confirm register</x-button>
                    </form>
                @endunless
            </div>
        </x-card>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Connections" description="API keys for Claude (AI drafting) and Africa’s Talking (SMS). Stored encrypted; a value in .env takes priority." icon="key-round" id="connections">
                <form method="post" action="{{ route('system.secrets') }}" class="space-y-4" autocomplete="off">
                    @csrf
                    @foreach ($secrets as $name => $secret)
                        <div>
                            <label for="secret-{{ $name }}" class="label flex items-center justify-between gap-2">
                                <span>{{ $secret['label'] }}</span>
                                @if ($secret['env'])<x-badge tone="info">From .env</x-badge>@elseif ($secret['hint'])<x-badge tone="good" icon="check">Set {{ $secret['hint'] }}</x-badge>@else<x-badge>Not set</x-badge>@endif
                            </label>
                            <div class="flex gap-2">
                                <input id="secret-{{ $name }}" name="{{ $name }}" type="{{ str_contains($name, 'key') ? 'password' : 'text' }}" class="input min-w-0 flex-1" @disabled($secret['env']) placeholder="{{ $secret['hint'] ? 'Leave blank to keep' : '' }}" autocomplete="off">
                                @if ($secret['hint'] && ! $secret['env'])
                                    <button name="remove" value="{{ $name }}" class="btn btn-ghost btn-icon flex-none" aria-label="Remove {{ $secret['label'] }}" onclick="return confirm('Remove this key?')"><x-icon name="trash-2" /></button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <x-button icon="check">Save keys</x-button>
                </form>
                <div class="mt-6 border-t border-line pt-4">
                    <p class="label">Africa’s Talking callback URLs</p>
                    <p class="hint mb-3">In the Africa’s Talking dashboard, paste these under SMS → Callback URLs. Keep them private.</p>
                    @foreach ($smsUrls as $label => $url)
                        <div class="mb-3 last:mb-0" x-data="copy(@js($url))">
                            <p class="mb-1 text-xs text-muted">{{ $label }}</p>
                            <div class="flex gap-2">
                                <input type="text" readonly value="{{ $url }}" class="input num min-w-0 flex-1 text-xs" aria-label="{{ $label }} URL" x-on:focus="$el.select()">
                                <button type="button" class="btn btn-secondary btn-icon flex-none" x-on:click="copy()" :aria-label="copied ? 'Copied' : 'Copy'"><x-icon name="copy" /></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
            <x-card title="AI usage this month" description="Every Claude request is logged with its tokens and cost (US dollars)." icon="sparkles" id="ai">
                <div class="grid grid-cols-3 gap-4">
                    <x-stat label="Spent" :value="'$'.number_format($ai['month'], 2)" :hint="$ai['budget'] > 0 ? 'of $'.number_format($ai['budget']).' budget' : 'no limit'" />
                    <x-stat label="Requests" :value="number_format($ai['calls'])" :hint="$ai['failed'] ? $ai['failed'].' failed' : null" />
                    <x-stat label="From cache" :value="($ai['input'] + $ai['cacheRead']) > 0 ? round(100 * $ai['cacheRead'] / ($ai['input'] + $ai['cacheRead'])).'%' : '—'" hint="of input tokens" />
                </div>
                @if ($ai['budget'] > 0)<x-progress class="mt-4" :value="min($ai['month'], $ai['budget'])" :max="$ai['budget']" :tone="$ai['month'] >= $ai['budget'] * 0.8 ? 'warn' : 'brand'" label="Budget used" />@endif
                @if ($ai['byPurpose']->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($ai['byPurpose'] as $row)<x-badge>{{ ucfirst($row->purpose) }}: {{ $row->n }} · ${{ number_format((float) $row->cost, 2) }}</x-badge>@endforeach
                    </div>
                @endif
                @if ($ai['recent']->isNotEmpty())
                    <div class="mt-4 divide-y divide-line border-t border-line">
                        @foreach ($ai['recent'] as $call)
                            <div class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0 truncate"><span class="font-medium">{{ ucfirst($call->purpose) }}</span> <span class="text-subtle">· {{ $call->user?->name ?? 'Automatic' }} · {{ $call->created_at->diffForHumans() }}</span></span>
                                @if ($call->status === 'ok')<span class="num flex-none text-muted">${{ number_format($call->cost_usd, 4) }}</span>@else<x-badge tone="bad">{{ $call->status }}</x-badge>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
                <p class="hint mt-3">Model: {{ $ai['model'] }}. Set the budget in Settings → Messaging and AI.</p>
            </x-card>
            <x-card title="Notifications" description="Web Push to phones and computers: the 7 AM brief, new tasks, quiet wards, security issues." icon="bell" id="push">
                @if ($pushConfigured)
                    <p class="flex items-center gap-2 text-sm font-medium text-good"><x-icon name="circle-check" size="18" /> Set up. {{ $pushDevices }} {{ \Illuminate\Support\Str::plural('device', $pushDevices) }} subscribed.</p>
                    <x-button :href="route('push')" variant="secondary" size="sm" icon="bell" class="mt-4">My notifications</x-button>
                @else
                    <form method="post" action="{{ route('system.push-keys') }}">@csrf<x-button icon="bell">Set up notifications</x-button></form>
                    <p class="hint">Creates the keys once. Then each person turns notifications on for their device.</p>
                @endif
            </x-card>
            <x-card title="Ward map" description="Ward boundaries for the map (e.g. GRID3 Nigeria wards, CC BY 4.0), as GeoJSON. Without it, maps show LGA tiles." icon="map" id="ward-map">
                @if ($wardMap)
                    <p class="flex items-center gap-2 text-sm font-medium text-good"><x-icon name="circle-check" size="18" /> {{ count($wardMap['wards']) }} wards loaded · {{ $wardMap['attribution'] }}</p>
                    <form method="post" action="{{ route('system.ward-map') }}" class="mt-3">@csrf<input type="hidden" name="remove" value="1"><x-button variant="ghost" size="sm" icon="trash-2">Remove</x-button></form>
                @endif
                <form method="post" action="{{ route('system.ward-map') }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <input type="file" name="file" accept=".geojson,.json,application/geo+json,application/json" class="input" aria-label="GeoJSON file">
                    <x-input name="attribution" label="Source and licence" value="GRID3 Nigeria, CC BY 4.0" />
                    <x-button variant="secondary" size="sm" icon="upload">{{ $wardMap ? 'Replace' : 'Load' }} ward boundaries</x-button>
                </form>
            </x-card>
            <x-card title="Backup" description="Every table as CSV in an AES-256 encrypted zip. Passwords, tokens and API keys are left out; phone numbers stay encrypted with the app key." icon="download" id="backup">
                <form method="post" action="{{ route('system.backup') }}" class="space-y-4" autocomplete="off">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input name="password" type="password" label="Backup password" autocomplete="new-password" hint="At least 12 characters. It isn’t stored: without it the zip can’t be opened." required />
                        <x-input name="password_confirmation" type="password" label="Repeat it" autocomplete="new-password" required />
                    </div>
                    <x-button icon="download" variant="secondary">Download backup</x-button>
                </form>
                <p class="hint mt-3">Also keep the host’s own database backups (DirectAdmin → Create/Restore Backups) and a copy of <code>APP_KEY</code> from <code>.env</code>.</p>
            </x-card>
            <x-card title="Privacy" description="Delete-my-data requests and the retention rule." icon="shield-check">
                <p class="text-sm">{{ $openRequests }} {{ \Illuminate\Support\Str::plural('request', $openRequests) }} to check.</p>
                <p class="mt-2 text-sm text-muted">
                    @if ($retentionDone)
                        Voter personal data was erased under the retention rule.
                    @elseif ($retention)
                        Voter personal data is erased automatically from <strong class="text-ink">{{ $retention->format('j F Y') }}</strong>.
                    @else
                        Automatic deletion after the election is off (Settings → Privacy).
                    @endif
                </p>
                <x-button :href="route('data-requests')" variant="secondary" size="sm" icon="shield-check" class="mt-4">Data requests</x-button>
            </x-card>
            <x-card title="Data" icon="database">
                <dl class="divide-y divide-line text-sm">
                    @foreach ($counts as $label => $count)
                        <div class="flex justify-between py-2.5 first:pt-0"><dt class="text-muted">{{ $label }}</dt><dd class="num font-medium">{{ number_format($count) }}</dd></div>
                    @endforeach
                </dl>
            </x-card>
            <x-card title="Environment" icon="server">
                <dl class="divide-y divide-line text-sm">
                    @foreach ($environment as $label => $value)
                        <div class="flex justify-between py-2.5 first:pt-0"><dt class="text-muted">{{ $label }}</dt><dd class="num font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>
