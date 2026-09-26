<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\PagePost;
use App\Support\Audit;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Our own pages: posts and their engagement, typed in or imported from a
 * CSV export of the page's insights. (Reading other people's Facebook
 * conversations isn't possible: Meta closed that access.)
 */
class PagePostController extends Controller
{
    public function index(Request $request): View
    {
        $platform = $request->query('platform');
        $posts = PagePost::query()->with('author')
            ->when(array_key_exists((string) $platform, config('messaging.platforms')), fn ($query) => $query->where('platform', $platform))
            ->latest('posted_at');

        $recent = (clone $posts)->where('posted_at', '>=', now()->subDays(28))->get();
        $weekly = collect(range(7, 0))->map(function ($ago) {
            $start = Time::now()->startOfWeek()->subWeeks($ago);

            return (int) PagePost::query()->whereBetween('posted_at', [$start->copy()->utc(), $start->copy()->addWeek()->utc()])->get()->sum(fn ($post) => $post->engagement());
        })->all();

        return view('posts.index', [
            'posts' => $posts->paginate(25)->withQueryString(),
            'platform' => $platform,
            'stats' => [
                'posts' => $recent->count(),
                'reach' => (int) $recent->sum('reach'),
                'engagement' => (int) $recent->sum(fn ($post) => $post->engagement()),
                'weekly' => $weekly,
            ],
            'topics' => $recent->whereNotNull('topic')->groupBy('topic')->map(fn ($rows) => ['n' => $rows->count(), 'avg' => (int) round($rows->avg(fn ($post) => $post->engagement()))])->sortByDesc('avg'),
            'best' => $recent->sortByDesc(fn ($post) => $post->engagement())->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(config('messaging.platforms')))],
            'posted_at' => ['required', 'date'],
            'text' => ['required', 'string', 'max:500'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'topic' => ['nullable', Rule::in(array_keys(config('messaging.narrative_topics')))],
            'reach' => ['nullable', 'integer', 'min:0'],
            'reactions' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
        ]);

        PagePost::create([...$data, 'posted_at' => Time::parse($data['posted_at'], local: true) ?? now(), 'created_by' => $request->user()->id]);

        return back()->with('status', 'Post logged.');
    }

    public function update(Request $request, PagePost $post): RedirectResponse
    {
        $post->update($request->validate([
            'reach' => ['nullable', 'integer', 'min:0'],
            'reactions' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('status', 'Engagement updated.');
    }

    public function destroy(PagePost $post): RedirectResponse
    {
        $post->delete();

        return back()->with('status', 'Post removed from the log.');
    }

    /**
     * CSV with a header row. Recognised columns (any order, case-insensitive):
     * date / posted / publish time, message / text / title, link / permalink,
     * reach, reactions / likes, comments, shares, platform, topic.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($cell) => Str::of((string) $cell)->lower()->replace("\u{FEFF}", '')->trim()->toString(), fgetcsv($handle, escape: '\\') ?: []);
        $find = fn (array $names) => collect($header)->search(fn ($column) => collect($names)->contains(fn ($name) => str_contains($column, $name)));
        $columns = [
            'date' => $find(['publish time', 'posted', 'date', 'time']),
            'text' => $find(['message', 'text', 'title', 'description']),
            'link' => $find(['permalink', 'link', 'url']),
            'reach' => $find(['reach', 'impressions']),
            'reactions' => $find(['reactions', 'likes']),
            'comments' => $find(['comments']),
            'shares' => $find(['shares']),
            'platform' => $find(['platform']),
            'topic' => $find(['topic']),
        ];

        if ($columns['date'] === false || $columns['text'] === false) {
            fclose($handle);

            return back()->with('error', 'The CSV needs at least a date column and a message (or title) column.');
        }

        $added = $skipped = 0;
        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $cell = fn (string $key) => $columns[$key] === false ? null : trim((string) ($row[$columns[$key]] ?? ''));
            $number = fn (string $key) => is_numeric(str_replace(',', '', (string) $cell($key))) ? (int) str_replace(',', '', (string) $cell($key)) : null;

            try {
                $date = Time::parse($cell('date'), local: true);
            } catch (Throwable) {
                $date = null;
            }
            if (! $date || blank($cell('text'))) {
                $skipped++;

                continue;
            }

            $platform = Str::lower((string) $cell('platform'));
            $topic = Str::lower((string) $cell('topic'));
            $link = $cell('link');

            PagePost::query()->updateOrCreate(
                ['platform' => array_key_exists($platform, config('messaging.platforms')) ? $platform : 'facebook', 'posted_at' => $date, 'text' => Str::limit((string) $cell('text'), 495)],
                [
                    'link' => $link && filter_var($link, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $link) ? Str::limit($link, 495, '') : null,
                    'topic' => array_key_exists($topic, config('messaging.narrative_topics')) ? $topic : null,
                    'reach' => $number('reach'), 'reactions' => $number('reactions'), 'comments' => $number('comments'), 'shares' => $number('shares'),
                    'created_by' => $request->user()->id,
                ],
            );
            $added++;
        }
        fclose($handle);

        Audit::record('posts.import', "Imported {$added} page posts from a CSV", rows: $added);

        return back()->with('status', "{$added} posts imported".($skipped ? ", {$skipped} rows skipped (no date or text)" : '').'.');
    }
}
