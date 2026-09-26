{{-- A ward in the winning / losing lists. Expects $row. --}}
<li class="flex items-center gap-3 py-2.5">
    <span class="size-2.5 flex-none rounded-full" style="background: {{ \App\Services\Intelligence::ZONES[$row['zone']]['color'] }}"></span>
    <a href="{{ route('areas.ward', [$row['lga_slug'], $row['slug']]) }}" class="min-w-0 flex-1 hover:text-brand-fg">
        <span class="block truncate text-sm font-medium">{{ $row['name'] }}</span>
        <span class="block truncate text-xs text-subtle">{{ $row['lga'] }}</span>
    </a>
    <span class="flex flex-none flex-col items-end">
        <span class="num text-sm font-semibold">{{ $row['share'] }}%</span>
        @if ($row['trend'] !== null)<x-delta :value="$row['trend']" suffix=" pts" />@endif
    </span>
</li>
