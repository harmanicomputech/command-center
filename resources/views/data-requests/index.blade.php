<x-layouts.app title="Data requests">
    <x-page-header title="Delete-my-data requests" eyebrow="Admin" description="People can ask the campaign to delete their details (Nigeria Data Protection Act). Erasing removes the name, number, community, notes and location, keeps anonymous counts, and adds the number to the SMS opt-out list.">
        <x-slot:actions>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'request-new')">Record a request</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert :tone="$retention ? 'info' : 'warn'" :title="$retention ? 'Retention: '.($retentionDone ? 'done' : 'voter personal data is erased automatically from '.$retention->format('j F Y')) : 'Automatic deletion after the election is switched off'" class="mb-6">
        @if ($retentionDone)
            Voter personal data was erased on {{ \App\Support\Time::local(\App\Support\Time::parse($retentionDone), 'j F Y') }}. Anonymous counts remain.
        @else
            Set the number of days after the election in <a href="{{ route('settings') }}" class="font-medium underline">Settings → Privacy</a> (0 switches it off).
        @endif
    </x-alert>

    <section class="mb-8">
        <h2 class="mb-3 text-base font-semibold">To check <span class="num text-muted">({{ $pending->count() }})</span></h2>
        <div class="grid gap-3">
            @forelse ($pending as $item)
                <article class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $item->name ?: 'No name given' }}</p>
                        <p class="text-sm text-muted"><a href="tel:{{ $item->phone }}" class="num font-medium text-brand-fg">{{ \App\Support\Phone::local($item->phone) }}</a> · {{ \App\Models\DataRequest::CHANNELS[$item->channel] ?? $item->channel }} · {{ $item->created_at->diffForHumans() }}</p>
                        <p class="mt-1 text-xs text-subtle">{{ $item->matches() }} {{ Str::plural('registration', $item->matches()) }} with this number. Call it to confirm it’s them before erasing.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="post" action="{{ route('data-requests.erase', $item) }}" onsubmit="return confirm('Erase this person’s details now? This can’t be undone.')">@csrf<x-button size="sm" icon="trash-2">Confirmed: erase</x-button></form>
                        <form method="post" action="{{ route('data-requests.reject', $item) }}">@csrf<x-button size="sm" variant="ghost" icon="x">Close</x-button></form>
                    </div>
                </article>
            @empty
                <div class="card"><x-empty icon="shield-check" title="No open requests" description="Requests from the privacy page appear here. SMS “DELETE” requests are erased at once." compact /></div>
            @endforelse
        </div>
    </section>

    <x-table title="Handled" description="The last 30. Numbers are forgotten once a request is closed.">
        <table class="table">
            <thead><tr><th>Request</th><th class="hidden sm:table-cell">Channel</th><th>Outcome</th><th class="hidden md:table-cell">By</th></tr></thead>
            <tbody>
                @forelse ($closed as $item)
                    <tr>
                        <td><span class="font-medium">#{{ $item->id }}</span> <span class="text-xs text-subtle">{{ $item->handled_at?->diffForHumans() }}</span></td>
                        <td class="hidden sm:table-cell">{{ \App\Models\DataRequest::CHANNELS[$item->channel] ?? $item->channel }}</td>
                        <td><x-badge :tone="\App\Models\DataRequest::STATUSES[$item->status]['tone']">{{ \App\Models\DataRequest::STATUSES[$item->status]['label'] }}</x-badge> <span class="num text-xs text-muted">{{ $item->erased }} {{ Str::plural('record', $item->erased) }}</span></td>
                        <td class="hidden md:table-cell">{{ $item->handler?->name ?? 'Automatic' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-sm text-muted">None yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-table>

    <x-modal name="request-new" title="Record a request">
        <form method="post" action="{{ route('data-requests.store') }}" class="space-y-4">
            @csrf
            <p class="text-sm text-muted">For a request made in person or by phone, once you’re sure it’s them. It is erased straight away.</p>
            <x-input name="phone" type="tel" label="Their phone number" inputmode="tel" required />
            <x-input name="note" label="Note" placeholder="e.g. Asked at the Afikpo office" optional />
            <x-button icon="trash-2">Erase now</x-button>
        </form>
    </x-modal>
</x-layouts.app>
