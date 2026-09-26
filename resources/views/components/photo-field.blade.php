{{-- Take or choose a photo; it is shrunk on the phone and queued with the form. Use x-ref on the wrapper to read .blob. --}}
@props(['label' => 'Photo', 'optional' => true])
<div x-data="photoField()" data-photo-field {{ $attributes }}>
    <p class="label">{{ $label }}@if ($optional) <span class="font-normal text-subtle">(optional)</span>@endif</p>
    <template x-if="preview">
        <div class="relative overflow-hidden rounded-2xl border border-line">
            <img :src="preview" alt="The photo to send" class="max-h-72 w-full object-cover">
            <button type="button" x-on:click="clear()" class="absolute top-2 right-2 grid size-10 place-items-center rounded-full bg-black/60 text-white backdrop-blur" aria-label="Remove photo"><x-icon name="x" /></button>
        </div>
    </template>
    <label x-show="! preview" class="flex min-h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-[1.5px] border-dashed border-line-strong bg-surface p-5 text-center transition-colors hover:bg-surface-2">
        <span class="grid size-11 place-items-center rounded-full bg-brand-soft text-brand-fg">
            <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
            <x-icon name="upload" x-show="! busy" />
        </span>
        <span class="text-sm font-semibold">Take or choose a photo</span>
        <span class="text-xs text-subtle">It’s made smaller on your phone, and sends when there’s network.</span>
        <input type="file" accept="image/*" capture="environment" class="sr-only" x-on:change="pick($event)">
    </label>
</div>
