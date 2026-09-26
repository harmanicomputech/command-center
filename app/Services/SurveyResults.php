<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Support\Collection;

/**
 * Results of a survey within the viewer's area, overall and broken down by
 * LGA, ward, age band or occupation. The sample size (n) goes with every
 * figure; under 30 is marked "small sample".
 */
class SurveyResults
{
    public const BREAKDOWNS = ['lga_id' => 'LGA', 'ward_id' => 'Ward', 'age_band' => 'Age', 'occupation' => 'Occupation', 'gender' => 'Gender'];

    /**
     * @return Collection<int, SurveyResponse>
     */
    public function responses(Survey $survey, User $viewer): Collection
    {
        // Web, SMS and USSD answers may have no ward: statewide roles see them.
        return SurveyResponse::query()->where('survey_id', $survey->id)
            ->when(! $viewer->role->isStatewide(), fn ($query) => $query->inAreaOf($viewer))
            ->get(['id', 'ward_id', 'lga_id', 'age_band', 'occupation', 'gender', 'channel', 'answers', 'answered_at']);
    }

    /**
     * @return array{n: int, questions: list<array{question: SurveyQuestion, n: int, counts: array<string, int>, texts: list<string>, average: ?float}>, byChannel: array<string, int>}
     */
    public function summary(Survey $survey, Collection $responses): array
    {
        $questions = [];

        foreach ($survey->questions as $question) {
            $answered = $responses->filter(fn ($response) => isset($response->answers[(string) $question->id]));
            $counts = array_fill_keys(array_keys($question->choices()), 0);

            foreach ($answered as $response) {
                foreach ((array) $response->answers[(string) $question->id] as $value) {
                    isset($counts[$value]) && $counts[$value]++;
                }
            }

            $questions[] = [
                'question' => $question,
                'n' => $answered->count(),
                'counts' => $counts,
                'texts' => $question->type === 'text' ? $answered->sortByDesc('answered_at')->take(12)->map(fn ($r) => (string) $r->answers[(string) $question->id])->values()->all() : [],
                'average' => $question->type === 'rating' && $answered->isNotEmpty() ? round($answered->avg(fn ($r) => (int) $r->answers[(string) $question->id]), 1) : null,
            ];
        }

        return ['n' => $responses->count(), 'questions' => $questions, 'byChannel' => $responses->countBy('channel')->all()];
    }

    /**
     * One question's answers per group (LGA, ward, age band, occupation).
     *
     * @return list<array{label: string, n: int, counts: array<string, int>}>
     */
    public function breakdown(Survey $survey, Collection $responses, int $questionId, string $by): array
    {
        $question = $survey->questions->firstWhere('id', $questionId);
        if (! $question || $question->type === 'text' || ! array_key_exists($by, self::BREAKDOWNS)) {
            return [];
        }

        $names = match ($by) {
            'lga_id' => Lga::query()->pluck('name', 'id'),
            'ward_id' => Ward::query()->pluck('name', 'id'),
            'age_band' => collect(config('canvass.age_bands')),
            'occupation' => collect(config('canvass.occupations')),
            'gender' => collect(config('canvass.genders')),
        };

        return $responses->filter(fn ($response) => isset($response->answers[(string) $questionId]))
            ->groupBy(fn ($response) => $response->{$by} ?? 'unknown')
            ->map(function ($group, $key) use ($question, $questionId, $names) {
                $counts = array_fill_keys(array_keys($question->choices()), 0);
                foreach ($group as $response) {
                    foreach ((array) $response->answers[(string) $questionId] as $value) {
                        isset($counts[$value]) && $counts[$value]++;
                    }
                }

                return ['label' => $key === 'unknown' ? 'Not given' : ($names[$key] ?? $key), 'n' => $group->count(), 'counts' => $counts];
            })->sortByDesc('n')->values()->all();
    }

    /**
     * Responses per ward against the quota.
     *
     * @return Collection<int, array{ward: Ward, n: int}>
     */
    public function quotas(Survey $survey, Collection $responses): Collection
    {
        $counts = $responses->whereNotNull('ward_id')->countBy('ward_id');
        $wards = Ward::query()->with('lga')->get()->filter(fn (Ward $ward) => $survey->targets($ward));

        return $wards->map(fn (Ward $ward) => ['ward' => $ward, 'n' => (int) ($counts[$ward->id] ?? 0)])
            ->sortBy('n')->values();
    }

    /**
     * Voting-intention shares for the zone engine, per ward and per LGA:
     * the average weight of intention answers (same scale as canvassing).
     *
     * @return array{ward: array<int, array{n: int, share: float}>, lga: array<int, array{n: int, share: float}>}
     */
    public static function intentionShares(): array
    {
        $questions = SurveyQuestion::query()->where('type', 'intention')
            ->whereHas('survey', fn ($query) => $query->whereIn('status', [Survey::LIVE, Survey::CLOSED]))->pluck('id');

        if ($questions->isEmpty()) {
            return ['ward' => [], 'lga' => []];
        }

        $weights = collect(config('surveys.intention_options'))->map(fn ($option) => $option['weight']);
        $rows = [];

        SurveyResponse::query()->whereIn('survey_id', SurveyQuestion::query()->whereIn('id', $questions)->pluck('survey_id'))
            ->whereNotNull('ward_id')->get(['ward_id', 'lga_id', 'answers'])
            ->each(function (SurveyResponse $response) use ($questions, $weights, &$rows) {
                foreach ($questions as $id) {
                    $value = $response->answers[(string) $id] ?? null;
                    if ($value !== null && $weights->has($value)) {
                        $rows[] = ['ward' => $response->ward_id, 'lga' => $response->lga_id, 'weight' => $weights[$value]];
                    }
                }
            });

        $group = fn (string $key) => collect($rows)->groupBy($key)->map(fn ($items) => ['n' => $items->count(), 'share' => 100 * $items->avg('weight')])->all();

        return ['ward' => $group('ward'), 'lga' => $group('lga')];
    }
}
