<x-layouts.field title="My registrations" :back="route('field.home')">
    <p class="mb-4 text-sm text-muted">Numbers are hidden once they reach the server. Your coordinator verifies a sample by phone.</p>
    <div class="card divide-y divide-line">
        @forelse ($voters as $voter)
            <div class="flex items-center gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $voter->name ?? 'Erased on request' }}</p>
                    <p class="num text-xs text-subtle">{{ $voter->phoneMasked() }} · {{ $voter->ward?->name }}</p>
                    <p class="text-xs text-subtle">{{ \App\Support\Time::local($voter->captured_at, 'j M, g:i A') }}</p>
                </div>
                @include('field._status', ['voter' => $voter])
            </div>
        @empty
            <x-empty icon="user-plus" title="No registrations yet" description="Voters you register show here once they reach the server.">
                <x-button :href="route('field.register')" icon="plus">Register a voter</x-button>
            </x-empty>
        @endforelse
    </div>
    @if ($voters->hasPages())<div class="mt-4">{{ $voters->links() }}</div>@endif
</x-layouts.field>
