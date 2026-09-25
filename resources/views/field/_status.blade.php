@if ($voter->possible_duplicate)
    <x-badge tone="warn" dot>Checking</x-badge>
@elseif ($voter->status === 'verified')
    <x-badge tone="good" icon="badge-check">Verified</x-badge>
@elseif ($voter->status === 'invalid')
    <x-badge tone="bad" dot>Invalid</x-badge>
@else
    <x-badge dot>Unverified</x-badge>
@endif
