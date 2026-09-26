<?php

namespace App\Services\Ai;

use App\Models\Narrative;
use App\Models\NarrativeReport;
use App\Models\User;
use App\Support\Settings;

/**
 * AI grouping suggestions for the narratives feed: Claude reads the
 * ungrouped reports (what was said, where it was seen, topic and tone;
 * never who reported it) and proposes which belong together. A person
 * accepts or ignores each suggestion.
 */
class NarrativeClusterer
{
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'groups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'report_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'narrative_id' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']], 'description' => 'An existing narrative these belong to, or null for a new one.'],
                        'title' => ['type' => 'string', 'description' => 'A short neutral title for a new narrative.'],
                        'why' => ['type' => 'string'],
                    ],
                    'required' => ['report_ids', 'narrative_id', 'title', 'why'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['groups'],
        'additionalProperties' => false,
    ];

    public const SETTING = 'narratives.suggestions';

    public function __construct(private Claude $claude) {}

    /**
     * @return list<array{report_ids: list<int>, narrative_id: ?int, title: string, why: string}>
     */
    public function suggest(?User $user = null): array
    {
        $reports = NarrativeReport::query()->whereNull('narrative_id')->with(['lga:id,name'])->latest('seen_at')->limit(60)->get();
        $open = Narrative::query()->where('status', '!=', 'closed')->latest('updated_at')->limit(40)->get(['id', 'title', 'summary', 'topic']);

        if ($reports->count() < 2 && $open->isEmpty()) {
            $this->store([]);

            return [];
        }

        $lines = ['Group these reports of things people are saying into narratives (the same story or claim, even if worded differently). Only group reports that are clearly about the same thing; leave the rest out.', '', 'Existing narratives:'];
        foreach ($open as $narrative) {
            $lines[] = "- [{$narrative->id}] ".Scrub::text($narrative->title).($narrative->summary ? ': '.Scrub::text(mb_substr($narrative->summary, 0, 200)) : '');
        }
        $lines[] = '';
        $lines[] = 'Ungrouped reports:';
        foreach ($reports as $report) {
            $lines[] = "- [{$report->id}] ({$report->sourceLabel()}, {$report->topicLabel()}, {$report->toneLabel()}, ".($report->lga?->name ?? 'statewide').') '.Scrub::text(mb_substr($report->summary, 0, 300));
        }

        $answer = $this->claude->ask('clustering', Prompts::system(), implode("\n", $lines), self::SCHEMA, $user, 8000);
        $valid = $reports->pluck('id')->flip();
        $narratives = $open->pluck('id')->flip();

        $groups = collect($answer['data']['groups'] ?? [])->map(function ($group) use ($valid, $narratives) {
            $ids = collect($group['report_ids'] ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $valid->has($id))->unique()->values()->all();
            $target = isset($group['narrative_id']) && $narratives->has((int) $group['narrative_id']) ? (int) $group['narrative_id'] : null;

            return ['report_ids' => $ids, 'narrative_id' => $target, 'title' => mb_substr(trim((string) ($group['title'] ?? '')), 0, 160), 'why' => mb_substr(trim((string) ($group['why'] ?? '')), 0, 300)];
        })->filter(fn ($group) => $group['report_ids'] !== [] && ($group['narrative_id'] !== null || (count($group['report_ids']) >= 2 && $group['title'] !== '')))
            ->values()->all();

        $this->store($groups);

        return $groups;
    }

    /**
     * @return array{at: ?string, status: ?string, groups: list<array{report_ids: list<int>, narrative_id: ?int, title: string, why: string}>, error?: string}
     */
    public static function stored(): array
    {
        $value = json_decode((string) Settings::get(self::SETTING), true);

        return is_array($value) ? $value + ['at' => null, 'status' => null, 'groups' => []] : ['at' => null, 'status' => null, 'groups' => []];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    public function store(array $groups, string $status = 'ready', ?string $error = null): void
    {
        Settings::set(self::SETTING, json_encode(array_filter(['at' => now()->toIso8601String(), 'status' => $status, 'groups' => $groups, 'error' => $error], fn ($value) => $value !== null)));
    }
}
