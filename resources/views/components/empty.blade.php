{{-- A designed empty state: an illustrated icon, what this is, the next action. --}}
@props(['icon' => 'inbox', 'title', 'description' => null, 'compact' => false])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center '.($compact ? 'px-4 py-8' : 'px-6 py-12 sm:py-16')]) }}>
    <div class="relative mb-5 grid place-items-center" aria-hidden="true">
        <span class="absolute size-24 rounded-full bg-brand-softer"></span>
        <span class="absolute size-16 rounded-full bg-brand-soft"></span>
        <span class="relative grid size-12 place-items-center rounded-2xl border border-line bg-surface text-brand-fg shadow-card">
            <x-icon :name="$icon" size="22" />
        </span>
        <span class="absolute -top-1 -right-3 size-2.5 rounded-full bg-accent"></span>
        <span class="absolute -bottom-2 -left-4 size-1.5 rounded-full bg-brand/40"></span>
    </div>
    <h3 class="text-base font-semibold">{{ $title }}</h3>
    @if ($description)<p class="mt-1.5 max-w-sm text-sm text-muted">{{ $description }}</p>@endif
    @if (trim($slot) !== '')<div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
