{{-- The top 3 on a podium: 2nd, 1st, 3rd. $rows are leaderboard rows with 'rank', 'points' and 'user' or 'name'. --}}
@props(['rows', 'highlight' => null])
@php
    $top = $rows->take(3)->values();
    $order = [1 => $top[1] ?? null, 0 => $top[0] ?? null, 2 => $top[2] ?? null];
    $heights = [0 => 'h-28', 1 => 'h-20', 2 => 'h-14'];
    $medals = [0 => 'var(--accent)', 1 => '#a9b1ba', 2 => '#c98a55'];
@endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-3 items-end gap-2 sm:gap-4']) }}>
    @foreach ($order as $place => $row)
        @if ($row === null)
            <div class="flex flex-col items-center" aria-hidden="true"><div class="mt-2 w-full rounded-t-xl border border-dashed border-line {{ $heights[$place] }}"></div></div>
            @continue
        @endif
        @php
            $name = $row['user']->name ?? $row['name'];
            $isMe = $highlight !== null && ($row['user']->id ?? $row['id']) === $highlight;
        @endphp
        <div class="flex min-w-0 flex-col items-center text-center">
            <div class="relative mb-2">
                <x-avatar :name="$name" :size="$place === 0 ? 64 : 52" class="ring-4 {{ $isMe ? 'ring-brand' : 'ring-surface' }}" />
                <span class="absolute -right-1 -bottom-1 grid size-6 place-items-center rounded-full text-xs font-bold text-white shadow-raised" style="background: {{ $medals[$place] }}">{{ $row['rank'] }}</span>
            </div>
            <p class="w-full truncate text-sm font-semibold">{{ $name }}</p>
            <p class="num text-xs text-muted">{{ number_format($row['points']) }} pts</p>
            <div class="mt-2 w-full rounded-t-xl {{ $heights[$place] }} {{ $place === 0 ? 'bg-gradient-to-b from-accent/70 to-accent-soft' : 'bg-surface-3' }}"></div>
        </div>
    @endforeach
</div>
