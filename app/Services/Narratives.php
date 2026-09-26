<?php

namespace App\Services;

use App\Models\Narrative;
use App\Models\NarrativeReport;
use App\Support\Time;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Narratives: daily trend lines, and the "spiking" push alert when a
 * narrative collects many reports in a day.
 */
class Narratives
{
    /**
     * Reports per day for the last $days days (Lagos dates), per narrative.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, list<int>>
     */
    public function trends(Collection $ids, int $days = 14): array
    {
        $start = Time::now()->subDays($days - 1)->startOfDay();
        $counts = NarrativeReport::query()->whereIn('narrative_id', $ids)->where('seen_at', '>=', $start->copy()->utc())
            ->get(['narrative_id', 'seen_at'])
            ->groupBy('narrative_id')
            ->map(fn ($rows) => $rows->countBy(fn ($row) => $row->seen_at->copy()->setTimezone(Time::zone())->format('Y-m-d')));

        return $ids->mapWithKeys(fn ($id) => [$id => collect(range($days - 1, 0))
            ->map(fn ($ago) => (int) ($counts[$id][Time::now()->subDays($ago)->format('Y-m-d')] ?? 0))->all()])->all();
    }

    /**
     * Alert the leadership when a narrative gets spike_reports reports in
     * 24 hours (at most once a day per narrative).
     */
    public function checkSpike(Narrative $narrative): void
    {
        $recent = $narrative->reports()->where('seen_at', '>=', now()->subDay())->count();

        if ($recent < (int) config('messaging.spike_reports') || ($narrative->alerted_at && $narrative->alerted_at->gt(now()->subDay()))) {
            return;
        }

        $narrative->forceFill(['alerted_at' => now()])->save();

        PushAlerts::queue('narrative', [
            'title' => 'Spiking: '.Str::limit($narrative->title, 60),
            'body' => "{$recent} reports in the last 24 hours. ".$narrative->toneLabel().', about '.mb_strtolower($narrative->topicLabel()).'.',
            'url' => '/narratives/'.$narrative->id,
            'tag' => 'narrative-'.$narrative->id,
            'urgent' => $narrative->tone === 'negative',
        ]);
    }
}
