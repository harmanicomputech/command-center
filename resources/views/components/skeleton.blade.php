@props(['lines' => 3])
<div {{ $attributes->merge(['class' => 'space-y-3']) }} role="status" aria-busy="true" aria-label="Loading">
    @for ($i = 0; $i < $lines; $i++)
        <div class="skeleton h-4" style="width: {{ [92, 76, 84, 60, 70][$i % 5] }}%"></div>
    @endfor
</div>
