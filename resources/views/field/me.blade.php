<x-layouts.field title="Me">
    <section class="flex items-center gap-4">
        <x-avatar :name="$user->name" :size="56" />
        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold tracking-tight">{{ $user->name }}</h1>
            <p class="text-sm text-muted">{{ $user->role->label() }} · {{ $user->areaLabel() }}</p>
        </div>
    </section>

    <div class="mt-6 grid grid-cols-3 gap-3">
        <div class="card p-4 text-center"><p class="num text-2xl font-bold">{{ number_format($stats['total']) }}</p><p class="text-xs text-muted">Registered</p></div>
        <div class="card p-4 text-center"><p class="num text-2xl font-bold">{{ $stats['streak'] }}</p><p class="text-xs text-muted">Day streak</p></div>
        <div class="card p-4 text-center"><p class="num text-2xl font-bold">{{ $stats['rank'] ? '#'.$stats['rank'] : '—' }}</p><p class="text-xs text-muted">In ward</p></div>
    </div>

    <div class="mt-4 space-y-4">
        <div class="card divide-y divide-line">
            <a href="{{ route('field.registrations') }}" class="flex min-h-12 items-center justify-between gap-3 p-4 text-sm font-semibold">
                <span class="flex items-center gap-3"><x-icon name="user-round-check" class="text-subtle" />My registrations</span>
                <x-icon name="chevron-right" class="text-subtle" />
            </a>
            <a href="{{ route('field.leaderboard') }}" class="flex min-h-12 items-center justify-between gap-3 p-4 text-sm font-semibold">
                <span class="flex items-center gap-3"><x-icon name="trophy" class="text-subtle" />Leaderboard and badges</span>
                <x-icon name="chevron-right" class="text-subtle" />
            </a>
            <a href="{{ route('field.outbox') }}" class="flex min-h-12 items-center justify-between gap-3 p-4 text-sm font-semibold">
                <span class="flex items-center gap-3"><x-icon name="cloud-upload" class="text-subtle" />Waiting on this phone</span>
                <span class="num text-subtle" x-data x-text="$store.outbox.total"></span>
            </a>
            <a href="{{ route('privacy') }}" class="flex min-h-12 items-center justify-between gap-3 p-4 text-sm font-semibold">
                <span class="flex items-center gap-3"><x-icon name="shield-check" class="text-subtle" />Privacy notice</span>
                <x-icon name="chevron-right" class="text-subtle" />
            </a>
        </div>
        <div class="card divide-y divide-line">
            <div class="flex items-center justify-between gap-4 p-4">
                <span class="flex items-center gap-3 text-sm font-semibold"><x-icon name="moon" class="text-subtle" />Dark mode</span>
                <input type="checkbox" class="switch" x-data :checked="$store.theme.dark" x-on:change="$store.theme.set($event.target.checked ? 'dark' : 'light')" aria-label="Dark mode">
            </div>
            <button type="button" x-data x-show="$store.install.available" x-cloak x-on:click="$store.install.prompt()" class="flex w-full items-center gap-3 p-4 text-left text-sm font-semibold">
                <x-icon name="download" class="text-subtle" />Install the app on this phone
            </button>
            <div x-data x-show="$store.install.ios && ! $store.install.installed" x-cloak class="p-4 text-sm">
                <p class="font-semibold">Add to your Home Screen</p>
                <p class="mt-1 text-muted">In Safari, tap Share, then <strong>Add to Home Screen</strong>. The app then opens full screen and works offline.</p>
            </div>
        </div>

        <details class="card group">
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 p-4 text-sm font-semibold">
                <span class="flex items-center gap-3"><x-icon name="key-round" class="text-subtle" />Change my PIN</span>
                <x-icon name="chevron-down" class="text-subtle transition-transform group-open:rotate-180" />
            </summary>
            <form method="post" action="{{ route('account.password') }}" class="space-y-4 px-4 pb-4">
                @csrf @method('put')
                <x-input name="current_password" type="password" inputmode="numeric" label="Current PIN" autocomplete="current-password" required />
                <x-input name="password" type="password" inputmode="numeric" label="New PIN" autocomplete="new-password" required hint="4 to 6 digits." />
                <x-input name="password_confirmation" type="password" inputmode="numeric" label="New PIN again" autocomplete="new-password" required />
                <x-button class="w-full" size="lg">Change PIN</x-button>
            </form>
        </details>

        <form method="post" action="{{ route('logout') }}" data-logout>
            @csrf
            <x-button variant="secondary" size="lg" icon="log-out" class="w-full">Sign out</x-button>
        </form>
        <p class="text-center text-xs text-subtle">Only sign out on a shared phone. You need network to sign back in.</p>
    </div>
</x-layouts.field>
