@php $maxTopic = max(1, (int) $topics->max('avg')); @endphp
<x-layouts.app title="Our pages">
    <x-page-header title="Our pages" eyebrow="Engage" description="Posts on the campaign’s own pages and how they did. Type them in, or import the CSV export from the page’s insights.">
        <x-slot:actions>
            <x-button type="button" variant="secondary" icon="upload" x-data x-on:click="$dispatch('open-modal', 'posts-import')">Import CSV</x-button>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'post-new')">Log a post</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi label="Posts, last 4 weeks" :value="$stats['posts']" icon="file-text" />
        <x-kpi label="Reach, last 4 weeks" :value="$stats['reach']" icon="eye" />
        <x-kpi label="Engagement, last 4 weeks" :value="$stats['engagement']" icon="thumbs-up" :spark="$stats['weekly']" hint="Reactions, comments and shares per week" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <x-table title="Posts" :description="$platform ? config('messaging.platforms.'.$platform) : 'All platforms'">
            <x-slot:actions>
                <form method="get" action="{{ route('posts') }}">
                    <label class="sr-only" for="p-platform">Platform</label>
                    <select id="p-platform" name="platform" class="input w-44" onchange="this.form.submit()">
                        <option value="">All platforms</option>
                        @foreach (config('messaging.platforms') as $key => $name)<option value="{{ $key }}" @selected($platform === $key)>{{ $name }}</option>@endforeach
                    </select>
                </form>
            </x-slot:actions>
            <table class="table">
                <thead><tr><th>Post</th><th class="hidden md:table-cell">Topic</th><th class="text-right">Reach</th><th class="hidden text-right sm:table-cell">Engagement</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr>
                            <td class="max-w-0 min-w-48">
                                <p class="truncate font-medium">@if ($post->link)<a href="{{ $post->link }}" target="_blank" rel="noopener noreferrer" class="hover:text-brand-fg">{{ $post->text }}</a>@else{{ $post->text }}@endif</p>
                                <p class="text-xs text-subtle">{{ $post->platformLabel() }} · {{ \App\Support\Time::local($post->posted_at, 'j M, g:i a') }}</p>
                            </td>
                            <td class="hidden md:table-cell">@if ($post->topic)<x-badge>{{ config('messaging.narrative_topics.'.$post->topic) }}</x-badge>@endif</td>
                            <td class="num text-right">{{ $post->reach !== null ? number_format($post->reach) : '—' }}</td>
                            <td class="num hidden text-right sm:table-cell">{{ number_format($post->engagement()) }}</td>
                            <td class="w-10 text-right">
                                <form method="post" action="{{ route('posts.destroy', $post) }}" onsubmit="return confirm('Remove this post from the log?')">@csrf @method('delete')<button class="btn btn-ghost btn-icon btn-sm text-subtle" aria-label="Remove"><x-icon name="trash-2" /></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="thumbs-up" title="No posts logged" description="Log posts by hand, or import the CSV your page’s insights export." compact /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-table>

        <aside class="space-y-4">
            @if ($best)
                <x-card title="Best post, last 4 weeks" icon="trophy">
                    <p class="line-clamp-4 text-sm">{{ $best->text }}</p>
                    <p class="num mt-2 text-xs text-subtle">{{ number_format($best->engagement()) }} engagements · {{ $best->platformLabel() }}</p>
                </x-card>
            @endif
            <x-card title="What works" icon="chart-column" description="Average engagement per post, by topic">
                @forelse ($topics as $topic => $row)
                    <div class="mb-2.5 last:mb-0">
                        <div class="mb-1 flex justify-between gap-3 text-sm"><span>{{ config('messaging.narrative_topics.'.$topic, $topic) }}</span><span class="num text-muted">{{ number_format($row['avg']) }} · {{ $row['n'] }} {{ Str::plural('post', $row['n']) }}</span></div>
                        <x-progress :value="$row['avg']" :max="$maxTopic" />
                    </div>
                @empty
                    <p class="text-sm text-muted">Tag posts with a topic to see which ones people engage with.</p>
                @endforelse
            </x-card>
            <p class="px-1 text-xs text-subtle">Only the campaign’s own pages: reading other people’s Facebook conversations isn’t possible (Meta closed that access in 2024).</p>
        </aside>
    </div>

    <x-modal name="post-new" title="Log a post" width="max-w-xl">
        <form method="post" action="{{ route('posts.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select name="platform" label="Platform" :options="config('messaging.platforms')" />
                <x-input name="posted_at" type="datetime-local" label="Posted" :value="\App\Support\Time::now()->format('Y-m-d\TH:i')" required />
            </div>
            <x-textarea name="text" label="Text (or a short description)" rows="2" maxlength="500" required />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="link" type="url" label="Link" placeholder="https://" optional />
                <x-select name="topic" label="Topic" :options="config('messaging.narrative_topics')" placeholder="None" optional />
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-input name="reach" type="number" label="Reach" min="0" optional />
                <x-input name="reactions" type="number" label="Reactions" min="0" optional />
                <x-input name="comments" type="number" label="Comments" min="0" optional />
                <x-input name="shares" type="number" label="Shares" min="0" optional />
            </div>
            <x-button icon="check">Save</x-button>
        </form>
    </x-modal>

    <x-modal name="posts-import" title="Import posts from a CSV">
        <form method="post" action="{{ route('posts.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <p class="text-sm text-muted">Needs a header row with a date column and a message (or title) column. Reach, reactions (or likes), comments, shares, link, platform and topic are picked up when present. Re-importing updates posts already logged.</p>
            <input name="file" type="file" accept=".csv,text/csv" class="input" required aria-label="CSV file">
            <x-button icon="upload">Import</x-button>
        </form>
    </x-modal>
</x-layouts.app>
