{{-- Shown while uploaded code waits for an admin to press Update database. --}}
<x-layouts.base title="Being updated">
    <main class="mx-auto grid min-h-dvh max-w-md place-items-center px-4 py-10 text-center">
        <div>
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand-fg"><x-icon name="refresh-cw" size="24" /></span>
            <h1 class="mt-5 text-2xl font-bold tracking-tight">The app is being updated</h1>
            <p class="mt-2 text-muted">Try again in a few minutes. Anything you saved on your phone is safe and will send when it’s back.</p>
            <a href="{{ url()->current() }}" class="btn btn-secondary mt-6">Try again</a>
        </div>
    </main>
</x-layouts.base>
