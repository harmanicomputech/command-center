@php
    $attending = $event->attendees->pluck('id')->all();
@endphp
<x-layouts.app :title="$event->title">
    <x-page-header :title="$event->title" :eyebrow="$event->typeLabel()" :back="route('events')"
        description="{{ \App\Support\Time::local($event->starts_at, 'l j F Y, g:i A') }} · {{ $event->place() }}{{ $event->venue ? ' · '.$event->venue : '' }}">
        <x-slot:actions>
            @if ($event->status === 'held')
                <x-badge tone="good" icon="circle-check">Held</x-badge>
            @elseif ($event->status === 'cancelled')
                <x-badge tone="bad">Cancelled</x-badge>
            @else
                <x-badge tone="info" dot>Planned</x-badge>
            @endif
            <x-button type="button" variant="secondary" icon="pencil" x-data x-on:click="$dispatch('open-modal', 'edit-event')">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" title="What happened" description="Record the outcome after the event. Team members marked here earn attendance points." icon="clipboard-list">
            <form method="post" action="{{ route('events.record', $event) }}" class="space-y-6" x-data="{ status: @js($event->status === 'planned' ? 'held' : $event->status) }">
                @csrf
                <div class="segmented" style="--cols: 2">
                    <label><input type="radio" name="status" value="held" x-model="status"><x-icon name="circle-check" size="18" />It was held</label>
                    <label><input type="radio" name="status" value="cancelled" x-model="status"><x-icon name="x" size="18" />Cancelled</label>
                </div>
                <div x-show="status === 'held'" class="space-y-6">
                    <x-input name="attendance" type="number" min="0" label="How many people came?" :value="$event->attendance" :hint="$event->expected ? 'Expected: '.number_format($event->expected) : null" class="max-w-xs" />
                    <fieldset>
                        <legend class="label">Team members who came</legend>
                        @if ($team->isEmpty())
                            <p class="text-sm text-muted">No coordinators or agents in this area yet.</p>
                        @else
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($team as $member)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3 transition-colors hover:bg-surface-2 has-[:checked]:border-brand has-[:checked]:bg-brand-softer">
                                        <input type="checkbox" name="attendees[]" value="{{ $member->id }}" class="checkbox" @checked(in_array($member->id, $attending, true))>
                                        <x-avatar :name="$member->name" :size="28" />
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $member->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                </div>
                <x-textarea name="notes" label="Notes" :value="$event->notes" optional rows="4" placeholder="What was said, what was agreed, what to follow up" />
                <div class="flex justify-end"><x-button icon="check">Save outcome</x-button></div>
            </form>
        </x-card>

        <div class="space-y-6">
            <x-card title="Summary" icon="calendar">
                <dl class="divide-y divide-line text-sm">
                    <div class="flex justify-between gap-4 py-2.5 first:pt-0"><dt class="text-muted">Expected</dt><dd class="num font-medium">{{ $event->expected ? number_format($event->expected) : '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Came</dt><dd class="num font-medium">{{ $event->attendance !== null ? number_format($event->attendance) : '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Team present</dt><dd class="num font-medium">{{ count($attending) }}</dd></div>
                    <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Planned by</dt><dd class="font-medium">{{ $event->author?->name ?? '—' }}</dd></div>
                </dl>
            </x-card>
            <form method="post" action="{{ route('events.destroy', $event) }}" x-data x-on:submit="if (! confirm('Delete this event?')) $event.preventDefault()">
                @csrf @method('delete')
                <x-button variant="ghost" icon="trash-2" class="w-full">Delete event</x-button>
            </form>
        </div>
    </div>

    <x-modal name="edit-event" title="Edit event" width="max-w-xl">
        <form method="post" action="{{ route('events.update', $event) }}" class="space-y-6">
            @csrf @method('put')
            @include('events._form', ['event' => $event])
            <x-textarea name="notes" label="Notes" :value="$event->notes" optional rows="3" />
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="check">Save</x-button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>
