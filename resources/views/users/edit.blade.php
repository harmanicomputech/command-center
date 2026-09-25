<x-layouts.app :title="'Edit '.$user->name">
    <x-page-header :title="$user->name" :eyebrow="$user->role->label()" :back="route('users')"
        description="Last seen {{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : 'never' }} · added {{ $user->created_at?->format('j M Y') }}" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="Account" class="lg:col-span-2">
            <form method="post" action="{{ route('users.update', $user) }}" class="space-y-6">
                @csrf @method('put')
                @include('users._fields', ['user' => $user])
                <div class="flex justify-end"><x-button icon="check">Save changes</x-button></div>
            </form>
        </x-card>

        <div class="space-y-6">
            <x-card title="{{ $user->isDisabled() ? 'Switched off' : 'Access' }}" description="{{ $user->isDisabled() ? 'This person can’t sign in.' : 'Switching off signs this person out on every device, e.g. for a lost phone.' }}">
                <form method="post" action="{{ route('users.toggle', $user) }}">
                    @csrf
                    <x-button :variant="$user->isDisabled() ? 'primary' : 'secondary'" :icon="$user->isDisabled() ? 'rotate-ccw' : 'lock'" class="w-full">
                        {{ $user->isDisabled() ? 'Switch back on' : 'Switch off' }}
                    </x-button>
                </form>
            </x-card>
            <x-card title="Delete account" description="Removes the account. Records they captured keep their author’s name.">
                <form method="post" action="{{ route('users.destroy', $user) }}" x-data x-on:submit="if (! confirm('Delete {{ addslashes($user->name) }}’s account?')) $event.preventDefault()">
                    @csrf @method('delete')
                    <x-button variant="danger" icon="trash-2" class="w-full">Delete account</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.app>
