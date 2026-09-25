<x-layouts.app title="Audit log">
    <x-page-header title="Audit log" eyebrow="Admin" description="Who did what: sign-ins, account changes, settings, imports, and every export and bulk action with its row count." />

    @if ($groups->isNotEmpty())
        <div class="no-scrollbar -mx-4 mb-4 flex gap-1.5 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <a href="{{ route('audit') }}" class="btn btn-sm {{ $action === '' ? 'btn-secondary' : 'btn-ghost' }}">All</a>
            @foreach ($groups as $group)
                <a href="{{ route('audit', ['action' => $group.'.']) }}" class="btn btn-sm {{ $action === $group.'.' ? 'btn-secondary' : 'btn-ghost' }}">{{ ucfirst($group) }}</a>
            @endforeach
        </div>
    @endif

    <x-table>
        <thead><tr><th>When</th><th>Who</th><th>What</th><th class="n">Rows</th><th>IP</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="num whitespace-nowrap text-muted" title="{{ \App\Support\Time::local($log->created_at, 'j M Y, g:i:s A') }}">{{ \App\Support\Time::local($log->created_at, 'j M, g:i A') }}</td>
                    <td class="whitespace-nowrap font-medium">{{ $log->user_name }}</td>
                    <td class="min-w-[260px]"><span class="mr-2 font-mono text-xs text-subtle">{{ $log->action }}</span>{{ $log->description }}</td>
                    <td class="n">{{ $log->rows === null ? '' : number_format($log->rows) }}</td>
                    <td class="num whitespace-nowrap text-subtle">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="scroll-text" title="Nothing logged yet" compact /></td></tr>
            @endforelse
        </tbody>
        @if ($logs->hasPages())
            <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
        @endif
    </x-table>
</x-layouts.app>
