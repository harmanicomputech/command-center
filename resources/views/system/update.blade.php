{{-- Uploaded code waiting for its database update: the one button, on a page that needs nothing new from the database. --}}
<x-layouts.base title="Update database">
    <main class="mx-auto grid min-h-dvh max-w-lg place-items-center px-4 py-10">
        <div class="card w-full p-6 sm:p-8">
            <span class="grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand-fg"><x-icon name="database" size="24" /></span>
            <h1 class="mt-5 text-2xl font-bold tracking-tight">Update the database</h1>
            <p class="mt-2 text-muted">A new version was uploaded. Its database changes haven’t run yet, so some pages can’t open until they do. It takes a few seconds and keeps all your data.</p>
            <p class="mt-4 text-sm text-subtle">{{ count($pending) }} {{ \Illuminate\Support\Str::plural('update', count($pending)) }} waiting.</p>
            @if (session('error'))
                <x-alert tone="bad" class="mt-4">{{ session('error') }}</x-alert>
            @endif
            <form method="post" action="{{ route('system.migrate') }}" class="mt-6">
                @csrf
                <x-button size="lg" icon="refresh-cw" class="w-full">Update database</x-button>
            </form>
        </div>
    </main>
</x-layouts.base>
