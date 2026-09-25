{{-- Underline tabs as links. $items: [[label, url, active(bool), count?]] --}}
@props(['items' => []])
<nav {{ $attributes->merge(['class' => 'no-scrollbar -mx-4 mb-6 flex gap-1 overflow-x-auto border-b border-line px-4 sm:mx-0 sm:px-0']) }} aria-label="Sections">
    @foreach ($items as $item)
        <a href="{{ $item[1] }}" @if ($item[2]) aria-current="page" @endif
            class="relative -mb-px flex h-11 flex-none items-center gap-2 border-b-2 px-3 text-sm font-semibold transition-colors {{ $item[2] ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink' }}">
            {{ $item[0] }}
            @if (isset($item[3]))<span class="num rounded-full bg-surface-2 px-1.5 py-0.5 text-xs text-muted">{{ $item[3] }}</span>@endif
        </a>
    @endforeach
</nav>
