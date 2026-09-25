<x-layouts.base title="Privacy notice">
    <main id="main" class="mx-auto max-w-2xl px-4 py-10 sm:py-16">
        <div class="mb-8 flex items-center gap-3"><x-logo :size="36" /><span class="text-lg font-bold tracking-tight">{{ $appName }}</span></div>
        <p class="eyebrow">Privacy notice</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">How the campaign uses your details</h1>
        <p class="mt-3 text-base text-muted">This notice explains what the campaign stores when a volunteer registers you, why, and your rights under the Nigeria Data Protection Act 2023.</p>

        <div class="mt-10 space-y-8 text-[15px] leading-7">
            <section>
                <h2 class="text-lg font-semibold">What we store</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-muted">
                    <li>Your name and, if you give it, your phone number (kept encrypted).</li>
                    <li>Your gender, age band (not your date of birth) and occupation.</li>
                    <li>Your ward and, if you say, your polling unit and community.</li>
                    <li>How you feel about the campaign and the issue that matters most to you.</li>
                    <li>That you agreed, when, and which volunteer registered you.</li>
                </ul>
                <p class="mt-3 text-muted">We never record your religion, ethnicity or voter card (PVC/VIN) number.</p>
            </section>
            <section>
                <h2 class="text-lg font-semibold">Why</h2>
                <p class="mt-2 text-muted">To understand what people in each ward care about, to plan the campaign, and to contact you about it. Your political opinion is sensitive personal data: we use it only with your consent, and only people working for the campaign in your area can see your record.</p>
            </section>
            <section>
                <h2 class="text-lg font-semibold">Your choices</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-muted">
                    <li><strong class="text-ink">Stop messages:</strong> reply STOP to any campaign SMS.</li>
                    <li><strong class="text-ink">Delete my data:</strong> ask the volunteer or coordinator who registered you, or any campaign office. We remove your personal details and keep only anonymous counts.</li>
                    <li><strong class="text-ink">See or correct your details:</strong> ask the same way.</li>
                </ul>
            </section>
            <section>
                <h2 class="text-lg font-semibold">How long</h2>
                <p class="mt-2 text-muted">Personal details are deleted after the election (by default 90 days after {{ \Illuminate\Support\Carbon::parse(config('campaign.election_date'))->format('j F Y') }}).</p>
            </section>
            <section>
                <h2 class="text-lg font-semibold">Who is responsible</h2>
                <p class="mt-2 text-muted">{{ \App\Support\Settings::get('privacy.controller') ?: 'The campaign' }} is the data controller.
                    @if (\App\Support\Settings::get('privacy.dpo'))Our data protection officer is {{ \App\Support\Settings::get('privacy.dpo') }}.@endif
                </p>
            </section>
        </div>
    </main>
</x-layouts.base>
