<?php

namespace App\Services\Ai;

use App\Models\Narrative;
use App\Models\NewsItem;
use App\Models\User;
use App\Services\DailyBrief;
use App\Services\Intelligence;
use App\Support\Settings;
use App\Support\Time;

/**
 * "What message to push next": each morning Claude reads the day's
 * aggregate numbers (zones, rising complaints, narratives, headlines) and
 * writes a short suggestion for the dashboard, labelled as a suggestion.
 */
class PushNextSuggester
{
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'suggestion' => ['type' => 'string', 'description' => 'Two to four sentences: the message to push next, where, and why.'],
        ],
        'required' => ['suggestion'],
        'additionalProperties' => false,
    ];

    public function __construct(private Claude $claude, private Intelligence $intel, private DailyBrief $brief) {}

    public function run(): ?string
    {
        if (! Claude::configured()) {
            return null;
        }

        $answer = $this->claude->ask('suggestion', Prompts::system(), $this->prompt(), self::SCHEMA, null, 4000);
        $text = trim((string) ($answer['data']['suggestion'] ?? ''));

        if ($text !== '') {
            Settings::set('brief.suggestion', mb_substr($text, 0, 900));
            Settings::set('brief.suggestion_at', now()->toIso8601String());
        }

        return $text ?: null;
    }

    public function prompt(): string
    {
        $viewer = new User(['role' => 'admin']);
        $wards = $this->intel->wards($viewer);
        $lines = ['Today is '.Time::now()->format('l j F Y').'. Here are the campaign\'s numbers this morning (aggregates only).', ''];

        $zones = $wards->countBy('zone');
        $lines[] = 'Wards by zone: '.collect(['stronghold', 'swing', 'weak', 'unknown'])->map(fn ($zone) => $zone.' '.($zones[$zone] ?? 0))->implode(', ');

        $lines[] = 'Top priority wards (many voters, uncertain, reachable):';
        foreach ($wards->sortByDesc('priority')->take(6) as $row) {
            $lines[] = sprintf('- %s, %s: %s, our share %s%%, trend %s', $row['name'], $row['lga'], $row['zone'], $row['share'] ?? '?', isset($row['trend']) ? sprintf('%+.1f', $row['trend']) : 'n/a');
        }

        $rising = $this->brief->risingIssues($viewer);
        if ($rising->isNotEmpty()) {
            $lines[] = 'Complaints rising this week (reports this week vs last): '.$rising->map(fn ($row) => "{$row['label']} {$row['week']} vs {$row['last']}")->implode(', ');
        }

        $narratives = Narrative::query()->where('status', '!=', 'closed')
            ->withCount(['reports as week' => fn ($query) => $query->where('seen_at', '>=', now()->subWeek())])
            ->orderByDesc('week')->limit(5)->get()->filter(fn ($narrative) => $narrative->week > 0);
        if ($narratives->isNotEmpty()) {
            $lines[] = 'Narratives people are repeating (reports this week):';
            foreach ($narratives as $narrative) {
                $lines[] = '- "'.Scrub::text($narrative->title).'" ('.$narrative->toneLabel().', '.$narrative->topicLabel().', status '.$narrative->statusLabel().'): '.$narrative->week;
            }
        }

        $headlines = NewsItem::query()->whereNotNull('keywords')->where('published_at', '>=', now()->subDays(2))->latest('published_at')->limit(5)->pluck('title');
        if ($headlines->isNotEmpty()) {
            $lines[] = 'Recent headlines that mention us or our keywords: '.$headlines->map(fn ($title) => '"'.$title.'"')->implode('; ');
        }

        $lines[] = '';
        $lines[] = 'In two to four plain sentences, suggest the one message the campaign should push next, to whom and where, and why these numbers point to it. It is a suggestion for the leadership to weigh, not an order.';

        return implode("\n", $lines);
    }
}
