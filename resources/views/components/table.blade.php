{{-- A table card. Wide tables scroll sideways inside the card, never the page. --}}
@props(['title' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'card min-w-0 overflow-hidden']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
            <div class="min-w-0">
                @if ($title)<h2 class="text-base font-semibold">{{ $title }}</h2>@endif
                @if ($description)<p class="mt-0.5 text-sm text-muted">{{ $description }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div class="table-wrap">
        <table class="table">{{ $slot }}</table>
    </div>
    @isset($footer)
        <footer class="border-t border-line px-5 py-3 text-sm sm:px-6">{{ $footer }}</footer>
    @endisset
</section>
