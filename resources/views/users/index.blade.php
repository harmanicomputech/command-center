<x-layouts.app title="Users">
    <x-page-header title="Users" eyebrow="Admin" description="Everyone who can sign in, and what they can see. Scope is enforced on the server for every page.">
        <x-slot:actions>
            <x-button type="button" icon="user-plus" x-data x-on:click="$dispatch('open-modal', 'add-user')">Add person</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="no-scrollbar -mx-4 flex gap-1.5 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <a href="{{ route('users', ['q' => $q ?: null]) }}" class="btn btn-sm {{ $role ? 'btn-ghost' : 'btn-secondary' }}">All <span class="num text-subtle">{{ $counts->sum() }}</span></a>
            @foreach (\App\Enums\UserRole::cases() as $option)
                <a href="{{ route('users', ['role' => $option->value, 'q' => $q ?: null]) }}" class="btn btn-sm {{ $role === $option ? 'btn-secondary' : 'btn-ghost' }}">{{ $option->label() }} <span class="num text-subtle">{{ $counts[$option->value] ?? 0 }}</span></a>
            @endforeach
        </div>
        <form method="get" action="{{ route('users') }}" class="w-full lg:w-72">
            @if ($role)<input type="hidden" name="role" value="{{ $role->value }}">@endif
            <x-input name="q" :value="$q" icon="search" placeholder="Search by name or email" aria-label="Search users" />
        </form>
    </div>

    <x-table>
        <thead>
            <tr><th>Name</th><th class="hidden sm:table-cell">Role</th><th class="hidden lg:table-cell">Area</th><th class="hidden md:table-cell">Sign-in</th><th class="hidden xl:table-cell">Last seen</th><th><span class="sr-only">Actions</span></th></tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr @class(['opacity-60' => $user->isDisabled()])>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$user->name" :size="32" />
                            <div class="min-w-0">
                                <p class="font-semibold whitespace-nowrap">{{ $user->name }}</p>
                                <p class="text-xs text-subtle sm:hidden">{{ $user->role->label() }}</p>
                                @if ($user->isDisabled())<x-badge tone="bad" class="mt-0.5">Switched off</x-badge>@endif
                            </div>
                        </div>
                    </td>
                    <td class="hidden whitespace-nowrap sm:table-cell"><x-badge :tone="$user->isAdmin() ? 'brand' : null">{{ $user->role->label() }}</x-badge></td>
                    <td class="hidden whitespace-nowrap text-muted lg:table-cell">{{ $user->areaLabel() }}</td>
                    <td class="hidden whitespace-nowrap text-muted md:table-cell">{{ $user->email ?? \App\Support\Phone::local($user->phone) }}</td>
                    <td class="hidden whitespace-nowrap text-muted xl:table-cell">{{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : 'Never' }}</td>
                    <td class="text-right"><x-button :href="route('users.edit', $user)" variant="ghost" size="sm" icon="pencil" aria-label="Edit {{ $user->name }}"><span class="hidden sm:inline">Edit</span></x-button></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="users" title="No one matches" description="Try another name, or clear the filter." compact /></td></tr>
            @endforelse
        </tbody>
        @if ($users->hasPages())
            <x-slot:footer>{{ $users->links() }}</x-slot:footer>
        @endif
    </x-table>

    <x-modal name="add-user" title="Add a person">
        <form method="post" action="{{ route('users.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="_form" value="create">
            @include('users._fields', ['user' => null])
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="check">Add person</x-button>
            </div>
        </form>
    </x-modal>
    @if ($errors->any() && old('_form') === 'create')
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'add-user'))"></div>
    @endif
</x-layouts.app>
