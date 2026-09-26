<x-layouts.app title="Policy brief">
    <x-page-header title="Policy brief" eyebrow="Messages" :back="route('messages')" description="The campaign’s positions, topic by topic. AI drafts promise only what is written here, so keep it specific: places, numbers, first steps.">
        <x-slot:actions>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'policy-new')">Add a brief</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat class="card p-5" label="Topics covered" :value="$documents->count()" />
        <x-stat class="card p-5" label="Words the AI reads" :value="number_format($words)" hint="Cached between drafts, so a long brief stays cheap." />
        <x-stat class="card p-5" label="Topics still empty" :value="$missing->count()" :hint="$missing->take(4)->implode(', ').($missing->count() > 4 ? '…' : '')" />
    </div>

    <div class="space-y-6">
        @forelse ($documents as $topic => $group)
            <section>
                <h2 class="eyebrow mb-2">{{ config('messaging.policy_topics.'.$topic, $topic) }}</h2>
                <div class="grid gap-3">
                    @foreach ($group as $document)
                        <article class="card p-5" x-data="{ editing: false }">
                            <div x-show="! editing">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <h3 class="font-semibold">{{ $document->title }}</h3>
                                    <div class="flex items-center gap-2">
                                        @unless ($document->active)<x-badge>Not used</x-badge>@endunless
                                        <x-button type="button" variant="ghost" size="sm" icon="pencil" x-on:click="editing = true">Edit</x-button>
                                    </div>
                                </div>
                                <p class="mt-2 line-clamp-6 text-sm whitespace-pre-line text-muted">{{ $document->body }}</p>
                                <p class="mt-3 text-xs text-subtle">Updated {{ $document->updated_at->diffForHumans() }}{{ $document->editor ? ' by '.$document->editor->name : '' }}</p>
                            </div>
                            <form x-show="editing" x-cloak method="post" action="{{ route('policies.update', $document) }}" class="space-y-4">
                                @csrf @method('put')
                                @include('messages._policy-fields', ['document' => $document, 'prefix' => 'p'.$document->id])
                                <div class="flex flex-wrap gap-2">
                                    <x-button icon="check">Save</x-button>
                                    <x-button type="button" variant="ghost" x-on:click="editing = false">Cancel</x-button>
                                </div>
                            </form>
                            <form x-show="editing" x-cloak method="post" action="{{ route('policies.destroy', $document) }}" class="mt-3 border-t border-line pt-3" onsubmit="return confirm('Delete this policy brief?')">
                                @csrf @method('delete')
                                <x-button variant="ghost" size="sm" icon="trash-2" class="text-bad">Delete</x-button>
                            </form>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="card"><x-empty icon="book-open" title="No policy briefs yet" description="Start with the candidate’s vision, then agriculture, youth jobs, markets and roads, water, security, education and health." /></div>
        @endforelse
    </div>

    <x-modal name="policy-new" title="Add a policy brief" width="max-w-2xl">
        <form method="post" action="{{ route('policies.store') }}" class="space-y-4">
            @csrf
            @include('messages._policy-fields', ['document' => null, 'prefix' => 'new'])
            <x-button icon="check" class="w-full sm:w-auto">Add</x-button>
        </form>
    </x-modal>
</x-layouts.app>
