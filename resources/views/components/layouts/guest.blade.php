{{-- Sign-in and public pages: a brand panel beside the form on wide screens. --}}
@props(['title' => null])
<x-layouts.base :title="$title">
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
        <aside class="relative hidden overflow-hidden lg:block" aria-hidden="true"
            style="background: radial-gradient(120% 90% at 0% 0%, color-mix(in oklab, var(--brand), white 10%) 0%, var(--brand) 45%, color-mix(in oklab, var(--brand), black 45%) 100%)">
            <svg class="absolute inset-0 h-full w-full opacity-[0.14]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="guest-grid" width="32" height="32" patternUnits="userSpaceOnUse"><path d="M32 0H0v32" fill="none" stroke="white" stroke-width="1"/></pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#guest-grid)"/>
            </svg>
            <div class="absolute -right-24 -bottom-24 size-[420px] rounded-full border-[40px] border-white/[0.06]"></div>
            <div class="absolute -right-10 -bottom-10 size-[240px] rounded-full border-[28px] border-white/[0.07]"></div>
            <div class="absolute top-[30%] right-[22%] size-3 rounded-full" style="background: var(--accent); box-shadow: 0 0 0 10px color-mix(in oklab, var(--accent), transparent 80%)"></div>
            <div class="relative flex h-full flex-col justify-between p-12 text-white xl:p-16">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M19.5 8.2A8.5 8.5 0 1 0 19.5 15.8" stroke="white" stroke-width="2.4" stroke-linecap="round"/><path d="M15.6 9.9a4.3 4.3 0 1 0 0 4.2" stroke="white" stroke-opacity=".7" stroke-width="2.2" stroke-linecap="round"/><circle cx="19.6" cy="12" r="2.3" fill="var(--accent)"/></svg>
                    </span>
                    <span class="text-lg font-bold tracking-tight">{{ $appName }}</span>
                </div>
                <div class="max-w-md">
                    <p class="text-sm font-semibold tracking-wide text-white/70 uppercase">Ebonyi · {{ \Illuminate\Support\Carbon::parse(config('campaign.election_date'))->format('j F Y') }}</p>
                    <p class="mt-3 text-4xl leading-[1.1] font-bold tracking-tight">Every ward. Every voice. One picture.</p>
                    <p class="mt-4 text-base text-white/75">The campaign's brain and its engine: field registrations, the structure, issues and intelligence, in one place.</p>
                </div>
                <p class="text-sm text-white/60">{{ max(0, \App\Support\Time::daysToElection()) }} days to election day</p>
            </div>
        </aside>
        <main id="main" class="flex min-w-0 flex-col items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-[400px]">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <x-logo :size="40" />
                    <span class="text-lg font-bold tracking-tight">{{ $appName }}</span>
                </div>
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.base>
