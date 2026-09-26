<x-layouts.app title="News" wide>
    <x-page-header title="News tracker" eyebrow="Engage" description="Headlines from the news sites and blogs on the list, checked every {{ config('messaging.news_every_minutes') }} minutes. Stories that mention the alert keywords are flagged and sent as notifications.">
        <x-slot:actions>
            <form method="post" action="{{ route('news.fetch') }}">@csrf<x-button variant="secondary" icon="refresh-cw">Check now</x-button></form>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'feed-new')">Add a feed</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="min-w-0">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-1.5">
                    <a href="{{ route('news', array_filter(['q' => $search])) }}" class="btn btn-sm {{ ! $alerts && ! request()->boolean('starred') ? 'btn-secondary' : 'btn-ghost' }}">All</a>
                    <a href="{{ route('news', array_filter(['alerts' => 1, 'q' => $search])) }}" class="btn btn-sm {{ $alerts ? 'btn-secondary' : 'btn-ghost' }}">Keyword alerts <span class="num rounded-full bg-accent-soft px-1.5 text-xs text-accent-fg">{{ $alertCount }}</span></a>
                    <a href="{{ route('news', ['starred' => 1]) }}" class="btn btn-sm {{ request()->boolean('starred') ? 'btn-secondary' : 'btn-ghost' }}">Starred</a>
                </div>
                <form method="get" action="{{ route('news') }}" class="sm:w-64">
                    @if ($alerts)<input type="hidden" name="alerts" value="1">@endif
                    <label class="sr-only" for="news-q">Search headlines</label>
                    <input id="news-q" name="q" value="{{ $search }}" class="input" placeholder="Search headlines" type="search">
                </form>
            </div>

            <div class="card divide-y divide-line">
                @forelse ($items as $item)
                    <article class="flex gap-4 p-4 sm:p-5">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-subtle">{{ $item->feed?->name }} · <time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->diffForHumans() }}</time></p>
                            <h2 class="mt-1 text-base font-semibold">
                                @if ($item->link)<a href="{{ $item->link }}" target="_blank" rel="noopener noreferrer" class="hover:text-brand-fg">{{ $item->title }}</a>@else{{ $item->title }}@endif
                            </h2>
                            @if ($item->summary)<p class="mt-1 line-clamp-2 text-sm text-muted">{{ $item->summary }}</p>@endif
                            @if ($item->keywords)
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($item->keywords as $word)<x-badge tone="accent" icon="bell">{{ $word }}</x-badge>@endforeach
                                </div>
                            @endif
                        </div>
                        <form method="post" action="{{ route('news.star', $item) }}" class="flex-none">
                            @csrf
                            <button class="btn btn-ghost btn-icon btn-sm {{ $item->starred ? 'text-accent' : 'text-subtle' }}" aria-label="{{ $item->starred ? 'Unstar' : 'Star' }}" aria-pressed="{{ $item->starred ? 'true' : 'false' }}"><x-icon name="star" /></button>
                        </form>
                    </article>
                @empty
                    <x-empty icon="newspaper" title="No stories yet" description="Add the RSS feeds of local and national news sites and blogs. Most sites have one at /feed or /rss." />
                @endforelse
            </div>
            @if ($items->hasPages())<div class="mt-4">{{ $items->links() }}</div>@endif
        </section>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <x-card title="Alert keywords" icon="bell">
                <div class="flex flex-wrap gap-1.5">
                    @forelse ($keywords as $word)<x-badge>{{ $word }}</x-badge>@empty<p class="text-sm text-muted">None yet.</p>@endforelse
                </div>
                @if (auth()->user()->role->value === 'admin')<a href="{{ route('settings') }}#messaging" class="mt-3 inline-block text-sm font-medium text-brand-fg">Change in Settings</a>@endif
            </x-card>
            <x-card title="Feeds" icon="layers" :padded="false">
                <div class="divide-y divide-line">
                    @forelse ($feeds as $feed)
                        <div class="flex items-center gap-3 px-5 py-3">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium {{ $feed->active ? '' : 'text-subtle line-through' }}">{{ $feed->name }}</span>
                                <span class="block truncate text-xs {{ $feed->last_error ? 'text-bad' : 'text-subtle' }}">{{ $feed->last_error ? 'Error: '.$feed->last_error : number_format($feed->items_count).' stories · '.($feed->fetched_at?->diffForHumans() ?? 'not checked yet') }}</span>
                            </span>
                            <x-dropdown align="right">
                                <x-slot:trigger><button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Feed options"><x-icon name="ellipsis" /></button></x-slot:trigger>
                                <form method="post" action="{{ route('news.feeds.toggle', $feed) }}">@csrf<button class="menu-item w-full">{{ $feed->active ? 'Pause' : 'Resume' }}</button></form>
                                <form method="post" action="{{ route('news.feeds.destroy', $feed) }}" onsubmit="return confirm('Remove this feed and its stories?')">@csrf @method('delete')<button class="menu-item w-full text-bad">Remove</button></form>
                            </x-dropdown>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-muted">No feeds yet.</p>
                    @endforelse
                </div>
            </x-card>
        </aside>
    </div>

    <x-modal name="feed-new" title="Add a news feed">
        <form method="post" action="{{ route('news.feeds.store') }}" class="space-y-4">
            @csrf
            <x-input name="name" label="Name" placeholder="e.g. A local news site" required />
            <x-input name="url" type="url" label="RSS or Atom feed address" placeholder="https://example.com/feed" hint="Usually the site address followed by /feed or /rss." required />
            <x-button icon="plus">Add and check</x-button>
        </form>
    </x-modal>
</x-layouts.app>
