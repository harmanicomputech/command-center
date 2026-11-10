{{-- The voter's face check (resources/js/face-check.js). Nested in registerForm: reads `consent`, writes `face`. --}}
<section x-data="faceCheck(@js($face))" class="rounded-2xl border-[1.5px] p-4" :class="errors.face ? 'border-bad bg-bad-soft' : 'border-line-strong bg-surface'" id="face-check">
    <p class="flex items-center gap-2 text-base font-semibold"><x-icon name="scan-face" size="20" class="text-brand-fg" /> Voter’s face</p>

    <template x-if="! face">
        <div>
            <p class="mt-1 text-sm text-muted">After they agree, point the camera at the voter. They turn their head, smile and blink when asked. It takes about 15 seconds and shows a real person was registered.</p>
            <button type="button" class="btn btn-primary btn-xl mt-4 w-full" x-on:click="start()" :disabled="! consent">
                <x-icon name="camera" /> Start face check
            </button>
            <p class="hint" x-show="! consent">Tick the voter’s agreement above first.</p>
            <p class="hint" x-show="consent && downloading" x-cloak>Getting the face check ready (up to <span x-text="megabytes"></span> MB, once)…</p>
            <p class="hint" x-show="consent && ready === true" x-cloak>Works without network on this phone.</p>
        </div>
    </template>

    <template x-if="face">
        <div class="mt-3 flex items-center gap-4">
            <template x-if="face.preview">
                <img :src="face.preview" alt="The voter’s face photo" class="size-20 flex-none rounded-xl object-cover ring-1 ring-line">
            </template>
            <template x-if="! face.preview">
                <span class="grid size-20 flex-none place-items-center rounded-xl bg-surface-2 text-subtle"><x-icon name="scan-face" size="28" /></span>
            </template>
            <div class="min-w-0 flex-1">
                <template x-if="face.result.status === 'passed'">
                    <p class="flex items-center gap-1.5 font-semibold text-good"><x-icon name="badge-check" size="18" /> Face check passed</p>
                </template>
                <template x-if="face.result.status !== 'passed'">
                    <p class="flex items-center gap-1.5 font-semibold text-warn"><x-icon name="camera" size="18" /> Photo only</p>
                </template>
                <p class="mt-0.5 text-sm text-muted" x-text="face.result.status === 'passed' ? 'Real person confirmed.' : 'Counts once a coordinator verifies it.'"></p>
                <button type="button" class="btn btn-ghost btn-sm -ml-2.5 mt-1" x-on:click="retake()" x-show="! face.existing"><x-icon name="refresh-cw" /> Take again</button>
            </div>
        </div>
    </template>
    <p class="field-error" x-show="errors.face" data-error x-cloak><x-icon name="circle-alert" /><span x-text="errors.face?.[0]"></span></p>

    {{-- The camera, full screen (teleported: the page's entrance animation would trap a fixed layer). --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex flex-col bg-black text-white" role="dialog" aria-modal="true" aria-labelledby="face-title" x-on:keydown.escape.window="open && close()">
            <div class="relative min-h-0 flex-1 overflow-hidden">
                <video id="face-video" playsinline muted autoplay class="absolute inset-0 size-full object-cover" :class="mirrored ? '-scale-x-100' : ''"></video>

                {{-- The oval the face goes in; the rest of the picture is dimmed. --}}
                <div class="pointer-events-none absolute top-1/2 left-1/2 aspect-[3/4] w-[68vw] max-w-[320px] -translate-x-1/2 -translate-y-[54%] rounded-[50%] border-[3px] transition-colors duration-300"
                    style="box-shadow: 0 0 0 200vmax rgb(0 0 0 / .55)"
                    :class="state === 'camera' && view.stage === 'challenge' ? 'border-accent' : (state === 'camera' && view.stage === 'capture' ? 'border-good' : 'border-white/80')"></div>

                <div class="absolute inset-x-0 top-0 flex items-center gap-2 p-3" style="padding-top: max(.75rem, env(safe-area-inset-top))">
                    <button type="button" class="grid size-12 place-items-center rounded-full bg-black/50" x-on:click="close()" aria-label="Close the face check"><x-icon name="x" /></button>
                    <p id="face-title" class="flex-1 text-center text-base font-semibold">Face check</p>
                    <button type="button" class="grid size-12 place-items-center rounded-full bg-black/50" x-show="cameras > 1 && state === 'camera'" x-on:click="flip()" aria-label="Switch camera"><x-icon name="switch-camera" /></button>
                    <span class="size-12" x-show="! (cameras > 1 && state === 'camera')"></span>
                </div>

                <div x-show="state === 'loading'" class="absolute inset-0 grid place-items-center">
                    <p class="flex items-center gap-2 rounded-full bg-black/60 px-4 py-2 text-sm"><x-icon name="loader-circle" class="animate-spin" /> Starting the camera…</p>
                </div>
            </div>

            <div class="bg-black px-5 pt-5 text-center" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                {{-- Following the prompts --}}
                <div x-show="state === 'camera' || state === 'loading'">
                    <div class="mb-3 flex justify-center gap-2" aria-hidden="true">
                        <template x-for="i in (view.total || 3)" :key="i">
                            <span class="h-1.5 w-8 rounded-full transition-colors" :class="i <= (view.done || 0) ? 'bg-accent' : 'bg-white/25'"></span>
                        </template>
                    </div>
                    <p class="min-h-[3.5rem] text-xl leading-tight font-bold" aria-live="assertive"
                        x-text="view.stage === 'challenge' ? view.prompt : (view.stage === 'capture' ? 'Look straight at the camera' : 'Fit the face in the oval')"></p>
                    <p class="mt-1 min-h-[1.25rem] text-sm text-white/75" aria-live="polite" x-text="view.hint || ''"></p>
                </div>

                {{-- Didn't pass --}}
                <div x-show="state === 'failed'" x-cloak>
                    <p class="text-xl font-bold">The face check didn’t pass</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-white/75">Make sure the face is well lit and fills the oval, and the voter follows each prompt.</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <button type="button" class="btn btn-secondary btn-lg" x-on:click="photoOnly()">Photo only</button>
                        <button type="button" class="btn btn-primary btn-lg" x-on:click="retry()"><x-icon name="refresh-cw" /> Try again</button>
                    </div>
                </div>

                {{-- Can't run the check: a plain photo --}}
                <div x-show="state === 'fallback'" x-cloak>
                    <p class="text-lg font-bold">Take a clear photo of the face</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-white/75"><span x-text="reason"></span> A photo only counts once a coordinator verifies the registration.</p>
                    <div class="mt-4">
                        <button type="button" class="btn btn-primary btn-xl w-full" x-show="cameraWorks" x-on:click="snap()"><x-icon name="camera" /> Take photo</button>
                        <label class="btn btn-primary btn-xl w-full" x-show="! cameraWorks">
                            <x-icon name="camera" /> Open camera
                            <input type="file" accept="image/*" capture="environment" class="sr-only" x-on:change="pick($event)">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </template>
</section>
