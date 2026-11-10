{{-- The face check on a registration (App\Support\FaceCheck), for staff. --}}
@if ($voter->face_status === 'passed' && $voter->face_photo_at)
    <x-badge tone="good" icon="scan-face">Face check passed</x-badge>
@elseif ($voter->face_status === 'passed')
    <x-badge icon="scan-face">Face photo on its way</x-badge>
@elseif ($voter->face_status === 'photo_only')
    <x-badge tone="warn" icon="camera">Photo only</x-badge>
@elseif ($voter->face_status === 'missing')
    <x-badge tone="bad" icon="scan-face">No face check</x-badge>
@endif
