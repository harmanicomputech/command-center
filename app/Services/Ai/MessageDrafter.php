<?php

namespace App\Services\Ai;

use App\Models\Issue;
use App\Models\MessageDraft;
use App\Models\User;
use App\Services\Segments;
use Illuminate\Support\Str;

/**
 * Drafts three variants of a message for a segment. The prompt carries
 * the segment's description and counts, and the top field issues in its
 * area as counts per category and community: never a person's details.
 */
class MessageDrafter
{
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'variants' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string', 'description' => 'The message, ready to use.'],
                        'angle' => ['type' => 'string', 'description' => 'A few words on the approach, for the editor.'],
                    ],
                    'required' => ['text', 'angle'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['variants'],
        'additionalProperties' => false,
    ];

    public function __construct(private Claude $claude, private Segments $segments) {}

    public function draft(MessageDraft $draft): MessageDraft
    {
        $author = $draft->author ?? new User(['role' => 'admin']);

        try {
            $answer = $this->claude->ask('draft', Prompts::system(), $this->prompt($draft, $author), self::SCHEMA, $draft->author);
        } catch (AiException $e) {
            $draft->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 255)])->save();

            return $draft;
        }

        $variants = collect($answer['data']['variants'] ?? [])
            ->filter(fn ($variant) => is_array($variant) && filled($variant['text'] ?? null))
            ->take(3)
            ->map(fn ($variant) => ['text' => trim((string) $variant['text']), 'angle' => trim((string) ($variant['angle'] ?? ''))])
            ->values()->all();

        $draft->forceFill($variants === []
            ? ['status' => 'failed', 'error' => 'Claude didn’t return any drafts. Try again.', 'ai_call_id' => $answer['call']->id]
            : ['status' => 'ready', 'variants' => $variants, 'error' => null, 'ai_call_id' => $answer['call']->id]
        )->save();

        return $draft;
    }

    /**
     * The per-request part of the prompt (after the cached policy brief).
     */
    public function prompt(MessageDraft $draft, User $viewer): string
    {
        $filters = $draft->filters ?? [];
        $summary = $this->segments->describe($viewer, $filters);
        $channel = config("messaging.channels.{$draft->channel}");
        $lines = [];

        $lines[] = 'Write 3 different variants of one message.';
        $lines[] = '';
        $lines[] = 'Audience: '.$draft->audience;
        $lines[] = 'People in this segment we have canvassed: '.number_format($summary['count']);

        foreach (Segments::DIMENSIONS as $key => [$title, $config]) {
            $counts = collect($summary['breakdowns'][$key] ?? [])->sortDesc()->take(5);
            if ($counts->isNotEmpty() && empty($filters[$key])) {
                $lines[] = $title.': '.$counts->map(fn ($n, $value) => $this->optionLabel($config, $value).' '.$n)->implode(', ');
            }
        }

        if (count($summary['lgas']) > 1 && count($summary['lgas']) <= 13) {
            $lines[] = 'By LGA: '.collect($summary['lgas'])->take(6)->map(fn ($n, $name) => "{$name} {$n}")->implode(', ');
        }

        if ($issues = $this->fieldIssues($filters, $viewer)) {
            $lines[] = '';
            $lines[] = 'Problems our field agents reported in this area (reports, with the communities that reported most):';
            array_push($lines, ...$issues);
        }

        $lines[] = '';
        $lines[] = 'Goal: '.Scrub::text($draft->goal);
        $lines[] = 'Channel: '.$channel['label'].' ('.$channel['hint'].'). Keep each variant under '.$channel['limit'].' characters.';
        $lines[] = 'Language: '.$draft->languageLabel();
        if ($draft->tone) {
            $lines[] = 'Tone: '.config("messaging.tones.{$draft->tone}", $draft->tone);
        }
        $lines[] = '';
        $lines[] = 'Make the three variants genuinely different in approach (for example: a local problem and our fix, a promise with a concrete next step, a call to take part). For each, give the text and a few words on its angle.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function fieldIssues(array $filters, User $viewer): array
    {
        $query = Issue::query()->inAreaOf($viewer)->where('status', '!=', 'rejected')->where('reported_at', '>=', now()->subDays(90));
        ! empty($filters['lga_id']) && $query->whereIn('lga_id', $filters['lga_id']);
        ! empty($filters['ward_id']) && $query->whereIn('ward_id', $filters['ward_id']);

        return $query->get(['category', 'community'])->groupBy('category')
            ->sortByDesc(fn ($rows) => $rows->count())->take(5)
            ->map(function ($rows, $category) {
                $places = $rows->pluck('community')->filter()->map(fn ($name) => Str::limit(Scrub::text((string) $name), 40, ''))
                    ->countBy()->sortDesc()->take(3)->map(fn ($n, $name) => "{$name} {$n}")->implode(', ');

                return '- '.config("field.issue_categories.{$category}", $category).': '.$rows->count().($places ? " ({$places})" : '');
            })->values()->all();
    }

    private function optionLabel(string $config, string|int $value): string
    {
        $label = config("{$config}.{$value}");

        return is_array($label) ? $label['label'] : (string) ($label ?? $value);
    }
}
