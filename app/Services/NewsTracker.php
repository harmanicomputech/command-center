<?php

namespace App\Services;

use App\Models\NewsFeed;
use App\Models\NewsItem;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * The news and blog tracker: reads the RSS or Atom feeds admins list,
 * keeps new headlines, and flags the ones that mention the alert keywords
 * (the candidate, opponents, issues). Run from the background runner.
 */
class NewsTracker
{
    /**
     * @return array{feeds: int, new: int, alerts: int}
     */
    public function fetchAll(): array
    {
        $totals = ['feeds' => 0, 'new' => 0, 'alerts' => 0];

        foreach (NewsFeed::query()->where('active', true)->orderBy('id')->get() as $feed) {
            [$new, $alerts] = $this->fetch($feed);
            $totals['feeds']++;
            $totals['new'] += $new;
            $totals['alerts'] += $alerts;
        }

        NewsItem::query()->where('starred', false)->where('published_at', '<', now()->subDays((int) config('messaging.news_keep_days')))->delete();

        if ($totals['alerts'] > 0) {
            PushAlerts::queue('news', [
                'title' => $totals['alerts'] === 1 ? 'A news story mentions your keywords' : "{$totals['alerts']} news stories mention your keywords",
                'body' => 'Open the news tracker to see them.',
                'url' => '/news?alerts=1',
                'tag' => 'news-alerts',
            ]);
        }

        return $totals;
    }

    /**
     * @return array{0: int, 1: int} new items, new keyword alerts
     */
    public function fetch(NewsFeed $feed): array
    {
        try {
            $body = Http::timeout(15)->withHeaders(['User-Agent' => 'CommandCenter/1.0 (news tracker)'])->get($feed->url)->throw()->body();
            $items = $this->parse($body);
        } catch (Throwable $e) {
            $feed->forceFill(['fetched_at' => now(), 'last_error' => Str::limit($e->getMessage(), 250)])->save();

            return [0, 0];
        }

        $keywords = self::keywords();
        $new = $alerts = 0;

        foreach ($items as $item) {
            if ($item['published_at']->lt(now()->subDays((int) config('messaging.news_keep_days')))) {
                continue;
            }

            $matched = self::match($item['title'].' '.$item['summary'], $keywords);
            $created = NewsItem::query()->firstOrCreate(
                ['guid_hash' => hash('sha256', $feed->id.'|'.$item['guid'])],
                ['news_feed_id' => $feed->id, 'title' => $item['title'], 'link' => $item['link'], 'summary' => $item['summary'], 'keywords' => $matched ?: null, 'published_at' => $item['published_at']],
            )->wasRecentlyCreated;

            if ($created) {
                $new++;
                $matched && $alerts++;
            }
        }

        $feed->forceFill(['fetched_at' => now(), 'last_error' => null])->save();

        return [$new, $alerts];
    }

    /**
     * @return list<array{guid: string, title: string, link: ?string, summary: string, published_at: Carbon}>
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new \RuntimeException('Not an RSS or Atom feed.');
        }

        $entries = [];
        $rss = $doc->channel->item ?? null;

        if ($rss !== null && count($rss) > 0) {
            foreach ($rss as $item) {
                $entries[] = [(string) ($item->guid ?: $item->link), (string) $item->title, (string) $item->link, (string) $item->description, (string) $item->pubDate];
            }
        } else {
            foreach ($doc->entry ?? [] as $entry) {
                $link = null;
                foreach ($entry->link as $candidate) {
                    if (in_array((string) $candidate['rel'], ['', 'alternate'], true)) {
                        $link = (string) $candidate['href'];
                        break;
                    }
                }
                $entries[] = [(string) ($entry->id ?: $link), (string) $entry->title, $link, (string) ($entry->summary ?: $entry->content), (string) ($entry->published ?: $entry->updated)];
            }
        }

        return collect($entries)
            ->filter(fn ($entry) => trim($entry[1]) !== '' && trim($entry[0]) !== '')
            ->take(100)
            ->map(fn ($entry) => [
                'guid' => mb_substr(trim($entry[0]), 0, 500),
                'title' => Str::limit(self::plain($entry[1]), 295),
                'link' => filter_var(trim((string) $entry[2]), FILTER_VALIDATE_URL) && preg_match('#^https?://#i', trim((string) $entry[2])) ? mb_substr(trim((string) $entry[2]), 0, 500) : null,
                'summary' => Str::limit(self::plain($entry[3]), 600),
                'published_at' => self::date($entry[4]),
            ])->values()->all();
    }

    /**
     * The alert keywords: the setting plus the candidate's name.
     *
     * @return list<string>
     */
    public static function keywords(): array
    {
        return collect(explode(',', (string) Settings::get('news.keywords')))
            ->push((string) Settings::get('campaign.candidate'))
            ->map(fn ($word) => trim($word))->filter(fn ($word) => mb_strlen($word) >= 3)
            ->unique(fn ($word) => mb_strtolower($word))->values()->all();
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    public static function match(string $text, array $keywords): array
    {
        return array_values(array_filter($keywords, fn ($word) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/iu', $text) === 1));
    }

    private static function plain(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    private static function date(string $value): Carbon
    {
        try {
            $date = $value !== '' ? Carbon::parse($value)->utc() : now();
        } catch (Throwable) {
            $date = now();
        }

        return $date->gt(now()->addHour()) ? now() : $date;
    }
}
