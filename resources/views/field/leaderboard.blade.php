<x-layouts.field title="Leaderboard">
    <h1 class="text-2xl font-bold tracking-tight">Leaderboard</h1>
    <p class="mt-1 text-sm text-muted">{{ $period === 'week' ? 'This week, since Monday. It starts again every Monday.' : 'All time.' }}</p>

    <div class="mt-4 grid grid-cols-2 gap-2">
        <div class="segmented" style="--cols: 2">
            <label><input type="radio" onclick="location.href='{{ route('field.leaderboard', ['period' => 'week', 'scope' => $scope]) }}'" @checked($period === 'week')>Week</label>
            <label><input type="radio" onclick="location.href='{{ route('field.leaderboard', ['period' => 'all', 'scope' => $scope]) }}'" @checked($period === 'all')>All</label>
        </div>
        <div class="segmented" style="--cols: 2">
            <label><input type="radio" onclick="location.href='{{ route('field.leaderboard', ['period' => $period, 'scope' => 'ward']) }}'" @checked($scope === 'ward')>Ward</label>
            <label><input type="radio" onclick="location.href='{{ route('field.leaderboard', ['period' => $period, 'scope' => 'lga']) }}'" @checked($scope === 'lga')>LGA</label>
        </div>
    </div>

    @if ($rows->sum('points') > 0)
        <x-podium :rows="$rows" :highlight="$user->id" class="mt-8" />
        <ol class="card mt-4 divide-y divide-line">
            @foreach ($rows->slice(3) as $row)
                <li class="flex items-center gap-3 px-4 py-3 {{ $row['user']->id === $user->id ? 'bg-brand-soft' : '' }}">
                    <span class="num w-7 text-center text-sm font-bold text-muted">{{ $row['rank'] }}</span>
                    <x-avatar :name="$row['user']->name" :size="32" />
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $row['user']->id === $user->id ? 'You' : $row['user']->name }}</span>
                    <span class="num text-sm font-semibold">{{ number_format($row['points']) }}</span>
                </li>
            @endforeach
        </ol>
    @else
        <div class="card mt-6"><x-empty icon="trophy" title="No points yet {{ $period === 'week' ? 'this week' : '' }}" description="Register voters, finish tasks and report issues to get on the board." compact /></div>
    @endif

    <h2 class="mt-8 mb-3 text-base font-semibold">My badges</h2>
    <div class="grid grid-cols-2 gap-3">
        @foreach (\App\Services\Badges::ALL as $key => [$name, $description, $icon])
            <div class="card flex items-center gap-3 p-3 {{ $badges[$key] ? '' : 'border-dashed bg-transparent shadow-none' }}">
                <span class="grid size-10 flex-none place-items-center rounded-full {{ $badges[$key] ? 'bg-accent-soft text-accent-fg' : 'bg-surface-2 text-subtle' }}"><x-icon :name="$icon" /></span>
                <span class="min-w-0"><span class="block truncate text-sm font-semibold {{ $badges[$key] ? '' : 'text-muted' }}">{{ $name }}@unless ($badges[$key])<span class="sr-only"> (not earned yet)</span>@endunless</span><span class="block text-xs leading-tight text-subtle">{{ $description }}</span></span>
            </div>
        @endforeach
    </div>

    {{-- My own row, pinned above the tab bar. --}}
    @if ($me)
        <div class="fixed inset-x-0 bottom-[76px] z-30 px-4 pb-safe">
            <div class="mx-auto flex max-w-xl items-center gap-3 rounded-2xl border border-brand bg-surface px-4 py-3 shadow-overlay">
                <span class="num w-7 text-center text-sm font-bold text-brand-fg">#{{ $me['rank'] }}</span>
                <x-avatar :name="$user->name" :size="32" />
                <span class="min-w-0 flex-1 truncate text-sm font-semibold">You</span>
                <span class="num text-sm font-bold">{{ number_format($me['points']) }} pts</span>
            </div>
        </div>
        <div class="h-16"></div>
    @endif
</x-layouts.field>
