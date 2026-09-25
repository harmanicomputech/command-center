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
