<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\NewsFeed;
use App\Models\NewsItem;
use App\Services\NewsTracker;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The news and blog tracker: headlines from the feeds admins list, with
 * the ones mentioning the alert keywords on top.
 */
class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $alerts = $request->boolean('alerts');
        $search = trim((string) $request->query('q'));

        return view('news.index', [
            'items' => NewsItem::query()->with('feed:id,name')
                ->when($alerts, fn ($query) => $query->whereNotNull('keywords'))
                ->when($request->boolean('starred'), fn ($query) => $query->where('starred', true))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('summary', 'like', "%{$search}%")))
                ->latest('published_at')->paginate(30)->withQueryString(),
            'feeds' => NewsFeed::query()->withCount('items')->orderBy('name')->get(),
            'alerts' => $alerts,
            'search' => $search,
            'keywords' => NewsTracker::keywords(),
            'alertCount' => NewsItem::query()->whereNotNull('keywords')->where('published_at', '>=', now()->subWeek())->count(),
        ]);
    }

    public function storeFeed(Request $request, NewsTracker $tracker): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:500'],
        ]);

        $feed = NewsFeed::create($data + ['active' => true]);
        Audit::record('news.feed', "Added the news feed “{$feed->name}”");
        [$new] = $tracker->fetch($feed);

        return back()->with($feed->last_error ? 'error' : 'status', $feed->last_error ? 'Feed added, but it couldn’t be read yet: '.$feed->last_error : "Feed added: {$new} stories.");
    }

    public function toggleFeed(NewsFeed $feed): RedirectResponse
    {
        $feed->update(['active' => ! $feed->active]);

        return back()->with('status', $feed->active ? 'Feed resumed.' : 'Feed paused.');
    }

    public function destroyFeed(NewsFeed $feed): RedirectResponse
    {
        $feed->delete();
        Audit::record('news.feed', "Removed the news feed “{$feed->name}”");

        return back()->with('status', 'Feed removed.');
    }

    public function fetch(NewsTracker $tracker): RedirectResponse
    {
        @set_time_limit(120);
        $totals = $tracker->fetchAll();

        return back()->with('status', "Checked {$totals['feeds']} feeds: {$totals['new']} new stories, {$totals['alerts']} keyword matches.");
    }

    public function star(NewsItem $item): RedirectResponse
    {
        $item->update(['starred' => ! $item->starred]);

        return back();
    }
}
