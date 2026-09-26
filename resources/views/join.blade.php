<x-layouts.base title="Volunteer">
    <main id="main" class="mx-auto max-w-xl px-4 py-10 sm:py-16">
        <div class="mb-8 flex items-center gap-3"><x-logo :size="36" /><span class="text-lg font-bold tracking-tight">{{ $appName }}</span></div>

        @if (session('joined'))
            <section class="card p-8 text-center">
                <div class="mx-auto mb-5 grid size-16 place-items-center rounded-full bg-brand text-on-brand"><x-icon name="check" size="30" /></div>
                <h1 class="text-2xl font-bold tracking-tight">Thank you for stepping up</h1>
                <p class="mx-auto mt-2 max-w-sm text-muted">A coordinator in your area will call you soon. Together we will build a better Ebonyi.</p>
            </section>
        @else
            <p class="eyebrow">Volunteer</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Join the movement</h1>
            <p class="mt-3 text-muted">Tell us where you are and how you’d like to help. A coordinator in your area will call you.</p>

            <form method="post" action="{{ route('join.store') }}" class="card mt-8 space-y-6 p-5 sm:p-6">
                @csrf
                <div class="hidden" aria-hidden="true"><label for="j-website">Website</label><input id="j-website" name="website" tabindex="-1" autocomplete="off"></div>
                <x-input name="name" label="Your name" autocomplete="name" required />
                <x-input name="phone" type="tel" label="Phone number" inputmode="tel" autocomplete="tel" placeholder="0803 123 4567" required />
                <x-select name="lga" label="LGA" :options="$lgas->pluck('name', 'id')->all()" placeholder="Choose your LGA" />
                <x-input name="ward" label="Ward or community" optional />
                <fieldset>
                    <legend class="label">How would you like to help?</legend>
                    <div class="mt-1 grid gap-2 sm:grid-cols-2">
                        @foreach (config('volunteers.help') as $key => $helpLabel)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3 text-sm hover:bg-surface-2">
                                <input type="checkbox" name="help[]" value="{{ $key }}" class="checkbox" @checked(in_array($key, (array) old('help', []), true))>
                                {{ $helpLabel }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-textarea name="message" label="Anything else?" rows="2" maxlength="500" optional />
                <x-checkbox name="consent" :label="config('volunteers.consent_text')" description="See our privacy notice for how we use your details." />
                <p class="-mt-4 text-sm"><a href="{{ route('privacy') }}" class="font-medium text-brand-fg underline">Privacy notice</a></p>
                <x-button size="lg" icon="send" class="w-full">Sign me up</x-button>
            </form>
        @endif
    </main>
</x-layouts.base>
