@php
    $colorGroups = [
        'Brand' => ['brand' => 'Brand', 'brand-strong' => 'Brand strong', 'brand-fg' => 'Brand text', 'brand-soft' => 'Brand soft', 'accent' => 'Accent', 'accent-fg' => 'Accent text', 'accent-soft' => 'Accent soft'],
        'Neutrals' => ['bg' => 'Background', 'surface' => 'Surface', 'surface-2' => 'Surface 2', 'surface-3' => 'Surface 3', 'border' => 'Hairline', 'border-strong' => 'Border', 'text' => 'Text', 'text-muted' => 'Muted', 'text-subtle' => 'Subtle'],
        'Status' => ['good' => 'Good', 'good-soft' => 'Good soft', 'warn' => 'Warn', 'warn-soft' => 'Warn soft', 'bad' => 'Bad', 'bad-soft' => 'Bad soft', 'info' => 'Info', 'info-soft' => 'Info soft'],
        'Parties' => ['party-apc' => 'APC', 'party-pdp' => 'PDP', 'party-lp' => 'LP', 'party-other' => 'Others'],
        'Sequential ramp' => ['seq-0' => '0 · none', 'seq-1' => '1', 'seq-2' => '2', 'seq-3' => '3', 'seq-4' => '4', 'seq-5' => '5'],
        'Diverging ramp' => ['div-strong' => 'Stronghold', 'div-lean' => 'Leaning', 'div-swing' => 'Swing', 'div-weakish' => 'Leaning away', 'div-weak' => 'Weak', 'div-unknown' => 'Unknown'],
    ];
    $sizes = [44 => 'Hero 44', 36 => 'Hero 36', 28 => 'Page title 28', 22 => 'Section 22', 18 => 'Lead 18', 16 => 'Body 16', 14 => 'UI 14', 12 => 'Caption 12'];
    $sections = ['Colour', 'Type', 'Shape', 'Icons', 'Buttons', 'Forms', 'Feedback', 'Data', 'Field', 'Engage', 'Layout', 'Motion'];
@endphp
<x-layouts.app title="Design system" wide>
    <x-page-header title="Design system" eyebrow="Admin · living style guide"
        description="Every token and component the app is built from. Change the brand in resources/css/tokens.css and everything here follows. Toggle dark mode to check both themes.">
        <x-slot:actions>
            <x-button type="button" variant="secondary" icon="moon" x-data x-on:click="$store.theme.toggle()">Toggle theme</x-button>
        </x-slot:actions>
    </x-page-header>

    <nav class="no-scrollbar sticky top-14 z-10 -mx-4 mb-8 flex gap-1 overflow-x-auto border-b border-line bg-bg/90 px-4 py-2 backdrop-blur lg:top-16 lg:mx-0 lg:px-0" aria-label="Sections">
        @foreach ($sections as $section)
            <a href="#{{ strtolower($section) }}" class="btn btn-ghost btn-sm flex-none">{{ $section }}</a>
        @endforeach
    </nav>

    <div class="space-y-14">
        {{-- Colour --}}
        <section id="colour" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Colour</h2>
            <p class="mt-1 text-sm text-muted">Tokens are CSS variables mapped into Tailwind (<code>bg-brand</code>, <code>text-muted</code>, <code>border-line</code>…). Status colours pair a strong tone with a soft background; meaning is never carried by colour alone.</p>
            <div class="mt-6 space-y-8">
                @foreach ($colorGroups as $group => $colors)
                    <div>
                        <p class="eyebrow mb-3">{{ $group }}</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                            @foreach ($colors as $token => $label)
                                <div class="card overflow-hidden">
                                    <div class="h-16 border-b border-line" style="background: var(--{{ $token }})"></div>
                                    <div class="p-3">
                                        <p class="text-[13px] font-semibold">{{ $label }}</p>
                                        <p class="font-mono text-[11px] text-subtle">--{{ $token }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Type --}}
        <section id="type" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Type</h2>
            <p class="mt-1 text-sm text-muted">Inter, self-hosted (works offline). Tabular numbers for every figure. Heavier weights for KPIs, regular for body.</p>
            <div class="card mt-6 divide-y divide-line">
                @foreach ($sizes as $size => $label)
                    <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                        <span class="w-32 flex-none text-xs text-subtle">{{ $label }}</span>
                        <span class="min-w-0 truncate {{ $size >= 28 ? 'font-bold tracking-tight' : ($size >= 18 ? 'font-semibold' : '') }}" style="font-size: {{ $size }}px; line-height: 1.25">
                            @if ($size >= 36)<span class="num">1,248,390</span>@else Every ward, every voice @endif
                        </span>
                    </div>
                @endforeach
                <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                    <p><span class="eyebrow">Eyebrow</span></p>
                    <p class="num text-sm">Tabular 1,111 / 8,888</p>
                    <p class="text-sm"><a href="#type" class="link">A text link</a></p>
                </div>
            </div>
        </section>

        {{-- Shape --}}
        <section id="shape" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Space, shape and depth</h2>
            <p class="mt-1 text-sm text-muted">An 8px grid; cards with a 16px radius, a soft layered shadow and a 1px hairline.</p>
            <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach (['shadow-xs' => 'XS: inputs', 'shadow-card' => 'Card', 'shadow-raised' => 'Raised: hover', 'shadow-overlay' => 'Overlay: menus'] as $shadow => $label)
                    <div class="grid h-28 place-items-center rounded-card border border-line bg-surface text-sm font-medium" style="box-shadow: var(--{{ $shadow }})">{{ $label }}</div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                @foreach ([8, 16, 24, 32, 40, 48, 64] as $space)
                    <div class="text-center"><div class="rounded bg-brand-soft" style="width: {{ $space }}px; height: {{ $space }}px"></div><p class="mt-1 text-[11px] text-subtle">{{ $space }}</p></div>
                @endforeach
            </div>
        </section>

        {{-- Icons --}}
        <section id="icons" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Icons</h2>
            <p class="mt-1 text-sm text-muted">Lucide, inline SVG, stroke 1.75: <code>&lt;x-icon name="map" /&gt;</code>. Add more in <code>scripts/icons.mjs</code>.</p>
            <div class="card mt-6 grid grid-cols-4 gap-1 p-3 sm:grid-cols-8 lg:grid-cols-12">
                @foreach (\App\Support\Icons::names() as $icon)
                    <div class="flex flex-col items-center gap-1.5 rounded-lg p-2 text-center hover:bg-surface-2" title="{{ $icon }}">
                        <x-icon :name="$icon" size="22" />
                        <span class="w-full truncate text-[10px] text-subtle">{{ $icon }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Buttons --}}
        <section id="buttons" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Buttons</h2>
            <div class="card mt-6 space-y-6 p-6">
                <div class="flex flex-wrap items-center gap-3">
                    <x-button type="button" icon="plus">Primary</x-button>
                    <x-button type="button" variant="accent" icon="sparkles">Accent</x-button>
                    <x-button type="button" variant="secondary">Secondary</x-button>
                    <x-button type="button" variant="soft" icon="check">Soft</x-button>
                    <x-button type="button" variant="ghost">Ghost</x-button>
                    <x-button type="button" variant="danger" icon="trash-2">Danger</x-button>
                    <x-button type="button" disabled>Disabled</x-button>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button type="button" size="sm">Small</x-button>
                    <x-button type="button">Medium</x-button>
                    <x-button type="button" size="lg">Large (48px, field)</x-button>
                    <x-button type="button" size="xl" icon="plus">Register a voter</x-button>
                    <x-button type="button" variant="secondary" square icon="settings" aria-label="Settings" />
                </div>
            </div>
        </section>

        {{-- Forms --}}
        <section id="forms" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Forms</h2>
            <p class="mt-1 text-sm text-muted">16px text on phones (no zoom on focus), 44–48px targets, errors in words with an icon. Field forms use segmented buttons, not dropdowns.</p>
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card space-y-5 p-6">
                    <x-input name="demo_name" label="Full name" placeholder="Chika Nwankwo" />
                    <x-input name="demo_phone" type="tel" label="Phone" icon="phone" placeholder="0803 123 4567" hint="Normalised to +234…" />
                    <div>
                        <label class="label" for="demo-error">With an error</label>
                        <input id="demo-error" class="input" value="0803" aria-invalid="true">
                        <p class="field-error"><x-icon name="circle-alert" />Enter a Nigerian mobile number, e.g. 0803 123 4567.</p>
                    </div>
                    <x-select name="demo_lga" label="LGA" :options="array_combine(config('campaign.lgas'), config('campaign.lgas'))" placeholder="Choose the LGA" />
                    <x-textarea name="demo_notes" label="Notes" optional placeholder="Anything the coordinator should know" />
                </div>
                <div class="card space-y-6 p-6">
                    <x-segmented name="demo_age" label="Age band" :options="['18-24' => '18–24', '25-34' => '25–34', '35-44' => '35–44', '45-59' => '45–59', '60+' => '60+']" value="25-34" :cols="3" />
                    <x-segmented name="demo_support" label="Support level" :options="['strong' => 'Strong supporter', 'leaning' => 'Leaning us', 'undecided' => 'Undecided']" value="leaning" :cols="3" />
                    <x-checkbox name="demo_consent" label="I agree that the campaign can store my details and contact me" description="Required. Consent text version 1." :checked="true" />
                    <x-checkbox name="demo_switch" label="Daily brief notifications" description="A push alert at 7 AM." switch :checked="true" />
                    <div class="flex items-center gap-3"><input type="radio" class="radio" name="demo_radio" checked aria-label="Radio on"><input type="radio" class="radio" name="demo_radio" aria-label="Radio off"><span class="text-sm text-muted">Radio</span></div>
                </div>
            </div>
        </section>

        {{-- Feedback --}}
        <section id="feedback" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Feedback and states</h2>
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="space-y-3">
                    <x-alert tone="info" title="Heads up">The register loaded from the bundled file.</x-alert>
                    <x-alert tone="good" title="Synced">12 registrations reached the server.</x-alert>
                    <x-alert tone="warn" title="Check the register">It totals about three times INEC’s 2023 figure.</x-alert>
                    <x-alert tone="bad" title="2 items failed to sync">
                        The phone number was invalid.
                        <x-slot:actions><x-button type="button" size="sm" variant="secondary">Fix now</x-button></x-slot:actions>
                    </x-alert>
                </div>
                <div class="space-y-6">
                    <div class="card p-5">
                        <p class="eyebrow mb-3">Badges</p>
                        <div class="flex flex-wrap gap-2">
                            <x-badge>Neutral</x-badge><x-badge tone="brand">Brand</x-badge><x-badge tone="accent">Accent</x-badge>
                            <x-badge tone="good" dot>Stronghold</x-badge><x-badge tone="warn" dot>Swing</x-badge><x-badge tone="bad" dot>Weak</x-badge><x-badge tone="info" icon="info">Info</x-badge>
                        </div>
                        <p class="eyebrow mt-5 mb-3">Sync pill</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="badge badge-good"><x-icon name="cloud-check" />All synced</span>
                            <span class="badge badge-info"><x-icon name="refresh-cw" />12 waiting</span>
                            <span class="badge badge-warn"><x-icon name="cloud-off" />Offline: 12 saved on this phone</span>
                            <span class="badge badge-bad"><x-icon name="circle-alert" />2 failed: tap to fix</span>
                        </div>
                        <p class="eyebrow mt-5 mb-3">Toasts and celebration</p>
                        <div class="flex flex-wrap gap-2">
                            <x-button type="button" size="sm" variant="secondary" x-data x-on:click="cc.toast('Saved. The voter is registered.')">Success toast</x-button>
                            <x-button type="button" size="sm" variant="secondary" x-data x-on:click="cc.toast('Couldn’t save: check the phone number.', 'bad')">Error toast</x-button>
                            <x-button type="button" size="sm" variant="secondary" x-data x-on:click="cc.confetti()">Confetti</x-button>
                            <x-button type="button" size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'demo')">Modal</x-button>
                        </div>
                    </div>
                    <div class="card p-5">
                        <p class="eyebrow mb-4">Skeleton loading</p>
                        <div class="flex items-center gap-3"><div class="skeleton size-10 rounded-full"></div><div class="flex-1"><x-skeleton :lines="2" /></div></div>
                    </div>
                </div>
            </div>
            <div class="card mt-6">
                <x-empty icon="user-round-check" title="No registrations yet" description="When agents start canvassing, their registrations appear here within seconds of syncing.">
                    <x-button type="button" icon="user-plus">Invite agents</x-button>
                    <x-button type="button" variant="secondary">Learn how</x-button>
                </x-empty>
            </div>
        </section>

        {{-- Data --}}
        <section id="data" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Data display</h2>
            <p class="mt-1 text-sm text-muted">KPI tiles: big number, change against last week with the sign in text (▲ +12%), a sparkline. Tables: sticky header, hairlines, right-aligned numbers, inline bars. Sample figures below are illustrative only.</p>
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-kpi label="Registrations this week" :value="4218" :delta="12.4" :spark="[3, 5, 4, 7, 6, 9, 11]" icon="user-round-check" />
                <x-kpi label="Active agents" :value="312" :delta="-3.1" :spark="[9, 8, 8, 7, 8, 7, 7]" icon="users" />
                <x-kpi label="Issues reported" :value="57" :delta="0" :spark="[2, 3, 2, 3, 3, 2, 3]" icon="triangle-alert" :invert="true" />
                <x-kpi label="Canvass support" :value="58.6" :decimals="1" suffix="%" :spark="[52, 53, 55, 54, 57, 58, 59]" icon="target" />
            </div>
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-table title="Wards" class="lg:col-span-2">
                    <thead><tr><th>Ward</th><th>Zone</th><th class="n">Registered</th><th class="w-40">Share</th></tr></thead>
                    <tbody>
                        @foreach ([['Sample ward A', 'good', 'Stronghold', 1840, 62], ['Sample ward B', 'warn', 'Swing', 1320, 47], ['Sample ward C', 'bad', 'Weak', 980, 31]] as [$ward, $tone, $zone, $count, $share])
                            <tr>
                                <td class="font-semibold">{{ $ward }}</td>
                                <td><x-badge :tone="$tone" dot>{{ $zone }}</x-badge></td>
                                <td class="n">{{ number_format($count) }}</td>
                                <td><div class="flex items-center gap-2"><x-progress :value="$share" class="flex-1" /><span class="num w-9 text-right text-xs font-semibold">{{ $share }}%</span></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                <div class="card flex flex-col items-center justify-center gap-4 p-6">
                    <x-progress-ring :value="14" :max="20" label="Registrations today">
                        <span class="num text-2xl leading-none font-bold">14</span><span class="mt-1 text-xs text-subtle">of 20</span>
                    </x-progress-ring>
                    <div class="flex -space-x-2">
                        @foreach (['Ada Obi', 'Chika Nwankwo', 'Emeka Eze', 'Ngozi Ali', 'Uche Agu'] as $name)<x-avatar :name="$name" :size="34" class="ring-2 ring-surface" />@endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- Gamification --}}
        <section id="field" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Field components</h2>
            <p class="mt-1 text-sm text-muted">The podium (top 3, rank medals, the viewer’s ring), badges, engagement levels and the photo picker (shrinks on the phone, queues offline). Sample names are illustrative.</p>
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card p-6">
                    <x-podium :rows="collect([['name' => 'Ada Obi', 'id' => 1, 'points' => 240, 'rank' => 1], ['name' => 'Emeka Eze', 'id' => 2, 'points' => 198, 'rank' => 2], ['name' => 'Ngozi Ali', 'id' => 3, 'points' => 150, 'rank' => 3]])" :highlight="2" class="mx-auto max-w-sm" />
                </div>
                <div class="card space-y-5 p-6">
                    <div class="flex flex-wrap gap-2"><x-engagement level="active" /><x-engagement level="occasional" /><x-engagement level="dormant" /></div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Services\Intelligence::ZONES as $zoneKey => $zoneInfo)
                            <x-badge :tone="$zoneInfo['tone']" dot>{{ $zoneInfo['label'] }}</x-badge>
                        @endforeach
                        <x-delta :value="4.2" suffix=" pts" label="this week" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (\App\Services\Badges::ALL as $key => [$name, $description, $icon])
                            <div class="card flex items-center gap-3 p-3 {{ $loop->index < 2 ? '' : 'border-dashed bg-transparent shadow-none' }}">
                                <span class="grid size-10 flex-none place-items-center rounded-full {{ $loop->index < 2 ? 'bg-accent-soft text-accent-fg' : 'bg-surface-2 text-subtle' }}"><x-icon :name="$icon" /></span>
                                <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $name }}</span><span class="block text-xs leading-tight text-subtle">{{ $description }}</span></span>
                            </div>
                        @endforeach
                    </div>
                    <x-photo-field />
                </div>
            </div>
        </section>

        {{-- Layout --}}
        <section id="engage" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Engage patterns</h2>
            <p class="mt-1 text-sm text-muted">The SMS preview bubble (with the live length and parts from <code>cc.sms()</code>), the AI suggestion label, and narrative tone and status badges. AI output is always labelled as a suggestion or draft.</p>
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card p-6" x-data="{ text: 'Town hall on Saturday, 10am. Reply STOP to opt out' }">
                    <div class="rounded-2xl bg-surface-2 p-4">
                        <div class="max-w-[260px] rounded-2xl rounded-bl-md bg-surface p-3 text-sm shadow-xs" x-text="text"></div>
                    </div>
                    <label for="demo-sms" class="label mt-4">Try Igbo letters (ị, ọ, ụ)</label>
                    <input id="demo-sms" class="input" x-model="text">
                    <p class="num mt-2 text-xs text-subtle"><span x-text="cc.sms(text).length"></span> characters · <span x-text="cc.sms(text).parts"></span> SMS<span x-show="cc.sms(text).unicode"> (Unicode)</span></p>
                </div>
                <div class="card space-y-5 p-6">
                    <div class="rounded-xl border border-brand/30 bg-brand-softer p-4">
                        <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-brand-fg"><x-icon name="sparkles" size="14" />AI suggestion, not a decision</p>
                        <p class="text-sm">An illustrative suggestion appears here each morning.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (config('messaging.narrative_tones') as $toneInfo)<x-badge :tone="$toneInfo['tone']">{{ $toneInfo['label'] }}</x-badge>@endforeach
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (config('messaging.narrative_statuses') as $statusInfo)<x-badge :tone="$statusInfo['tone']" dot>{{ $statusInfo['label'] }}</x-badge>@endforeach
                        @foreach (\App\Models\MessageDraft::STATUSES as $statusInfo)<x-badge :tone="$statusInfo['tone']" dot>{{ $statusInfo['label'] }}</x-badge>@endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="layout" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Layout</h2>
            <p class="mt-1 text-sm text-muted">Command Center: sidebar (Overview, Intelligence, Field, Engage, Admin) from 1024px, a drawer on tablets, a bottom tab bar on phones, ⌘K palette everywhere. Field Force: bottom tabs with the central Register button, 48px targets, one column up to 576px.</p>
            <div class="mt-6">
                <x-tabs :items="[['Overview', '#layout', true, 3], ['Wards', '#layout', false, 169], ['Team', '#layout', false]]" />
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-card title="Card with actions" description="A title, a description and actions on the right." icon="layers">
                    <x-slot:actions><x-button type="button" size="sm" variant="secondary">Action</x-button></x-slot:actions>
                    <p class="text-sm text-muted">Card body. Grid children get <code>min-width: 0</code>, so nothing forces a 360px phone to scroll sideways.</p>
                    <x-slot:footer><span>Footer</span><a href="#layout" class="link">A link</a></x-slot:footer>
                </x-card>
                <a href="#layout" class="card card-interactive block p-6">
                    <p class="text-base font-semibold">Interactive card</p>
                    <p class="mt-1 text-sm text-muted">Lifts on hover (220ms ease-out); presses back down.</p>
                </a>
            </div>
        </section>

        {{-- Motion --}}
        <section id="motion" class="scroll-mt-32">
            <h2 class="text-xl font-semibold tracking-tight">Motion</h2>
            <p class="mt-1 text-sm text-muted">150–250ms ease-out on hover and press. Numbers count up the first time they appear; skeletons shimmer while loading; pages cross-fade with the View Transitions API. All of it is switched off when the device asks for reduced motion.</p>
            <div class="card mt-6 flex flex-wrap items-center gap-8 p-6">
                <p class="num text-4xl font-bold tracking-tight"><span x-data x-count="98765">98,765</span></p>
                <div class="rise flex size-16 items-center justify-center rounded-2xl bg-brand-soft text-brand-fg"><x-icon name="sparkles" /></div>
            </div>
        </section>
    </div>

    <x-modal name="demo" title="A dialog">
        <p class="text-sm text-muted">Bottom sheet on phones, centred on larger screens. Esc or the scrim closes it; focus stays inside.</p>
        <div class="mt-6 flex justify-end gap-2">
            <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
            <x-button type="button" x-on:click="$dispatch('close-modal'); cc.toast('Done.')">Confirm</x-button>
        </div>
    </x-modal>
</x-layouts.app>
