<x-layouts.guest :title="$mode === 'setup' ? 'Set up' : 'Sign in'">
    @if ($mode === 'no-database')
        <x-alert tone="bad" title="The database isn’t reachable">
            Check the <code>DB_</code> settings in the <code>.env</code> file (in DirectAdmin’s File Manager), then reload this page.
        </x-alert>
    @elseif ($mode === 'setup')
        <h1 class="text-2xl font-bold tracking-tight">Set up {{ $appName }}</h1>
        <p class="mt-2 text-sm text-muted">Create the first admin account. This also sets up the database and loads the polling unit register.</p>
        <form method="post" action="{{ route('setup') }}" class="mt-8 space-y-5">
            @csrf
            <x-input name="setup_key" type="password" label="Setup key" autocomplete="off" required hint="ADMIN_PASSWORD in the .env file." />
            <x-input name="name" label="Your name" autocomplete="name" required />
            <x-input name="email" type="email" label="Email" autocomplete="email" required />
            <x-input name="password" type="password" label="Password" autocomplete="new-password" required hint="At least 10 characters." />
            <x-input name="password_confirmation" type="password" label="Password again" autocomplete="new-password" required />
            <x-button class="w-full" size="lg" icon-right="arrow-right">Create admin account</x-button>
        </form>
    @else
        <h1 class="text-2xl font-bold tracking-tight">Welcome back</h1>
        <p class="mt-2 text-sm text-muted">Sign in with your email, or your phone number and PIN.</p>
        <form method="post" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
            @csrf
            <x-input name="login" label="Email or phone number" autocomplete="username" autofocus required icon="user" placeholder="you@example.com or 0803…" />
            <div x-data="{ show: false }">
                <label for="f-password" class="label">Password or PIN</label>
                <div class="relative">
                    <x-icon name="lock" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-subtle" />
                    <input id="f-password" name="password" :type="show ? 'text' : 'password'" type="password" class="input pr-12 pl-10" autocomplete="current-password" required>
                    <button type="button" class="absolute top-1/2 right-1.5 grid size-9 -translate-y-1/2 place-items-center rounded-lg text-subtle hover:bg-surface-2 hover:text-ink" x-on:click="show = ! show" :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                        <x-icon name="eye" x-show="! show" size="18" />
                        <x-icon name="eye-off" x-show="show" x-cloak size="18" />
                    </button>
                </div>
            </div>
            <x-checkbox name="remember" label="Keep me signed in on this device" description="For your own phone or computer only." :checked="true" />
            <x-button class="w-full" size="lg" icon-right="arrow-right">Sign in</x-button>
        </form>
        <p class="mt-8 text-center text-sm text-muted">No account? Your coordinator invites you.</p>
    @endif
    @if (session('logged_out'))<div data-clear-cache hidden></div>@endif
</x-layouts.guest>
