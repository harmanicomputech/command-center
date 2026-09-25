@props(['title', 'description' => null, 'eyebrow' => null, 'back' => null])
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:mb-8 md:flex-row md:items-end md:justify-between']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink"><x-icon name="arrow-left" size="16" /> Back</a>
        @endif
        @if ($eyebrow)<p class="eyebrow mb-1.5">{{ $eyebrow }}</p>@endif
        <h1 class="text-xl font-semibold tracking-tight text-balance sm:text-2xl">{{ $title }}</h1>
        @if ($description)<p class="mt-1.5 max-w-2xl text-sm text-muted sm:text-base">{{ $description }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
