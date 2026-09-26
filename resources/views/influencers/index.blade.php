<x-layouts.app title="Influence">
    <x-page-header title="Influence" eyebrow="Intelligence"
        description="Who shapes opinion in each ward: traditional rulers, churches and mosques, age grades, town unions and market associations, with a contact and where the relationship stands. Recorded per community, never per voter.">
        <x-slot:actions>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'influencer')">Add a note</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="no-scrollbar -mx-4 mb-4 flex gap-1.5 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <a href="{{ route('influencers', array_filter(['kind' => $kind])) }}" class="btn btn-sm {{ $relationship ? 'btn-ghost' : 'btn-secondary' }}">All <span class="num text-subtle">{{ $counts->sum() }}</span></a>
        @foreach (config('structure.relationships') as $key => $option)
            <a href="{{ route('influencers', array_filter(['relationship' => $key, 'kind' => $kind])) }}" class="btn btn-sm {{ $relationship === $key ? 'btn-secondary' : 'btn-ghost' }}">{{ $option['label'] }} <span class="num text-subtle">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($influencers as $influencer)
            <article class="card flex flex-col p-5" x-data>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="grid size-10 flex-none place-items-center rounded-xl bg-accent-soft text-accent-fg"><x-icon name="landmark" size="19" /></span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $influencer->name }}</p>
                            <p class="truncate text-sm text-muted">{{ $influencer->kindLabel() }} · {{ $influencer->ward->fullName() }}</p>
                        </div>
                    </div>
                    <x-badge :tone="$influencer->relationshipTone()" dot>{{ $influencer->relationshipLabel() }}</x-badge>
                </div>
                @if ($influencer->notes)<p class="mt-3 line-clamp-3 text-sm text-muted">{{ $influencer->notes }}</p>@endif
                <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-4 text-sm">
                    <span class="min-w-0 truncate text-muted">
                        @if ($influencer->contact_name)<x-icon name="user" size="14" class="mr-1 inline -translate-y-px" />{{ $influencer->contact_name }}@else<span class="text-subtle">No contact yet</span>@endif
                    </span>
                    <div class="flex gap-1">
                        @if ($influencer->contact_phone)
                            <x-button :href="'tel:'.$influencer->contact_phone" variant="ghost" size="sm" square icon="phone" aria-label="Call {{ $influencer->contact_name }}" />
                        @endif
                        <x-button type="button" variant="ghost" size="sm" square icon="pencil" aria-label="Edit" x-on:click="$dispatch('open-modal', 'influencer-{{ $influencer->id }}')" />
                    </div>
                </div>
                <x-modal :name="'influencer-'.$influencer->id" title="Edit influence note">
                    <form method="post" action="{{ route('influencers.update', $influencer) }}" class="space-y-6">
                        @csrf @method('put')
                        @include('influencers._form', ['influencer' => $influencer])
                        <div class="flex flex-wrap justify-between gap-2">
                            <x-button type="submit" form="delete-influencer-{{ $influencer->id }}" variant="ghost" icon="trash-2">Delete</x-button>
                            <x-button icon="check">Save</x-button>
                        </div>
                    </form>
                    <form id="delete-influencer-{{ $influencer->id }}" method="post" action="{{ route('influencers.destroy', $influencer) }}" x-on:submit="if (! confirm('Delete this note?')) $event.preventDefault()">@csrf @method('delete')</form>
                </x-modal>
            </article>
        @empty
            <div class="card md:col-span-2 xl:col-span-3">
                <x-empty icon="landmark" title="No influence notes yet" description="Start with the traditional ruler and the biggest church or market association in each ward, and who the campaign talks to there.">
                    <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'influencer')">Add the first note</x-button>
                </x-empty>
            </div>
        @endforelse
    </div>
    @if ($influencers->hasPages())<div class="mt-4">{{ $influencers->links() }}</div>@endif

    <x-modal name="influencer" title="Add an influence note">
        <form method="post" action="{{ route('influencers.store') }}" class="space-y-6">
            @csrf
            @include('influencers._form')
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                <x-button icon="check">Add note</x-button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>
