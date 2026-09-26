{{-- Answer shares with the n, faded under the small-sample line. Expects $counts, $choices, $n. --}}
@php
    $small = $n < config('surveys.small_sample');
@endphp
<div @class(['opacity-55' => $small])>
    @foreach ($choices as $value => $label)
        @php $count = $counts[$value] ?? 0; @endphp
        <div class="mb-2 last:mb-0">
            <div class="mb-1 flex justify-between gap-3 text-sm"><span class="min-w-0 truncate">{{ $label }}</span><span class="num flex-none text-muted">{{ $n ? round(100 * $count / $n) : 0 }}% <span class="text-xs">({{ $count }})</span></span></div>
            <x-progress :value="$count" :max="max(1, $n)" />
        </div>
    @endforeach
</div>
