<x-layouts.app title="My account">
    <x-page-header title="My account" :description="$user->role->label().' · '.$user->areaLabel()" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Profile" icon="circle-user">
            <form method="post" action="{{ route('account.update') }}" class="space-y-5">
                @csrf @method('put')
                <x-input name="name" label="Name" :value="$user->name" required />
                <x-input name="email_display" label="Email" :value="$user->email" disabled hint="Ask an admin to change your email." />
                <x-button icon="check">Save</x-button>
            </form>
        </x-card>

        <x-card title="Password" icon="key-round">
            <form method="post" action="{{ route('account.password') }}" class="space-y-5">
                @csrf @method('put')
                <x-input name="current_password" type="password" label="Current password" autocomplete="current-password" required />
                <x-input name="password" type="password" label="New password" autocomplete="new-password" required hint="At least 10 characters." />
                <x-input name="password_confirmation" type="password" label="New password again" autocomplete="new-password" required />
                <x-button icon="check">Change password</x-button>
            </form>
        </x-card>

        <x-card title="Appearance" icon="palette" class="lg:col-span-2">
            <div class="grid grid-cols-3 gap-3 sm:max-w-md" x-data role="radiogroup" aria-label="Theme">
                @foreach (['system' => ['Auto', 'monitor'], 'light' => ['Light', 'sun'], 'dark' => ['Dark', 'moon']] as $value => [$label, $icon])
                    <button type="button" role="radio" x-on:click="$store.theme.set('{{ $value }}')" :aria-checked="$store.theme.choice === '{{ $value }}'"
                        class="flex flex-col items-center gap-2 rounded-xl border-[1.5px] p-4 text-sm font-semibold transition-colors"
                        :class="$store.theme.choice === '{{ $value }}' ? 'border-brand bg-brand-soft text-brand-fg' : 'border-line-strong hover:bg-surface-2'">
                        <x-icon :name="$icon" />{{ $label }}
                    </button>
                @endforeach
            </div>
        </x-card>
    </div>
</x-layouts.app>
