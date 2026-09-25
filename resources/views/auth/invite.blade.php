<x-layouts.guest title="Choose your PIN">
    @if (! $member)
        <x-empty icon="clock" title="This link has expired" description="Invite links work for 14 days and only once. Ask your coordinator to send a new one." />
    @else
        <h1 class="text-2xl font-bold tracking-tight">Welcome, {{ $member->firstName() }}</h1>
        <p class="mt-2 text-sm text-muted">You’re joining as a {{ strtolower($member->role->label()) }} in {{ $member->ward?->fullName() }}. Choose a PIN you’ll remember: you sign in with your phone number <span class="num font-semibold text-ink">{{ \App\Support\Phone::local($member->phone) }}</span> and this PIN.</p>
        <form method="post" action="{{ route('invite.accept', $token) }}" class="mt-8 space-y-5">
            @csrf
            <x-input name="pin" type="password" inputmode="numeric" label="PIN (4 to 6 digits)" autocomplete="new-password" required maxlength="6" />
            <x-input name="pin_confirmation" type="password" inputmode="numeric" label="PIN again" autocomplete="new-password" required maxlength="6" />
            <x-button size="lg" class="w-full" icon-right="arrow-right">Start</x-button>
        </form>
        <p class="mt-6 text-center text-xs text-subtle">You stay signed in on this phone for 30 days, so you can work without network.</p>
    @endif
</x-layouts.guest>
