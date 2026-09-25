<x-layouts.app :title="$ward->name">
    <x-page-header :title="$ward->name" :eyebrow="$lga->name.' LGA'" :back="route('areas.lga', $lga)"
        description="{{ $ward->polling_units_count }} polling units · {{ number_format($ward->registered_voters) }} registered voters" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="Team" icon="users" class="lg:col-span-1">
            @forelse ($team as $member)
                <div class="flex items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <x-avatar :name="$member->name" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $member->name }}</p>
                        <p class="text-xs text-subtle">{{ $member->role->label() }}</p>
                    </div>
                    @if ($member->last_seen_at && $member->last_seen_at->gt(now()->subDays(14)))
                        <x-badge tone="good" dot>Active</x-badge>
                    @else
                        <x-badge dot>Dormant</x-badge>
                    @endif
                </div>
            @empty
                <x-empty icon="user-plus" title="No one here yet" description="This ward has no coordinator or agents. It shows red on the dashboard until it does." compact />
            @endforelse
        </x-card>

        <x-table title="Polling units" class="lg:col-span-2">
            <thead>
                <tr><th>Code</th><th>Name</th><th class="n">Registered</th></tr>
            </thead>
            <tbody>
                @foreach ($units as $unit)
                    <tr>
                        <td class="num whitespace-nowrap text-muted">{{ $unit->inecCode() }}</td>
                        <td class="font-medium">{{ $unit->name ?? '—' }}</td>
                        <td class="n">{{ $unit->registered_voters === null ? '—' : number_format($unit->registered_voters) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </div>
</x-layouts.app>
