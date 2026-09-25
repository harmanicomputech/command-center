@props(['title' => null, 'description' => null, 'icon' => null, 'padded' => true])
<section {{ $attributes->merge(['class' => 'card min-w-0']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 {{ $padded ? '' : 'pb-4' }} sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                @if ($icon)
                    <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-lg bg-brand-soft text-brand-fg"><x-icon :name="$icon" size="18" /></span>
                @endif
                <div class="min-w-0">
                    @if ($title)<h2 class="text-base font-semibold">{{ $title }}</h2>@endif
                    @if ($description)<p class="mt-0.5 text-sm text-muted">{{ $description }}</p>@endif
                </div>
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['px-5 pb-5 sm:px-6 sm:pb-6' => $padded, 'pt-4' => $padded && $title, 'pt-5 sm:pt-6' => $padded && ! $title])>
        {{ $slot }}
    </div>
    @isset($footer)
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3.5 text-sm text-muted sm:px-6">{{ $footer }}</footer>
    @endisset
</section>
