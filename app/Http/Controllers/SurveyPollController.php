<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Services\SurveyRecorder;
use App\Support\Phone;
use App\Support\SurveyPoll;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Quick polls through Africa's Talking (the same account as Election
 * Shield's USSD service, on its own service code or shortcode).
 *
 * USSD: AT sends the whole input history each time (text=2*1*4), so every
 * request replays it from the first question; only the final END step
 * writes anything. SMS: "KEYWORD 2" answers the survey's first question.
 */
class SurveyPollController extends Controller
{
    /** USSD screens must stay under 182 characters. */
    private const SCREEN = 182;

    public function ussd(Request $request, string $token, SurveyRecorder $recorder): Response
    {
        abort_unless(SurveyPoll::valid($token), 404);

        $survey = Survey::query()->with('questions')->where('status', Survey::LIVE)->find(SurveyPoll::ussdSurveyId());
        if (! $survey || ! $survey->hasChannel('sms')) {
            return $this->end('No poll is running right now. Thank you.');
        }

        $phone = (string) $request->input('phoneNumber');
        if (($hash = Phone::hash($phone)) && SurveyResponse::query()->where('survey_id', $survey->id)->where('phone_hash', $hash)->exists()) {
            return $this->end('You have already answered this poll. Thank you!');
        }

        $questions = $survey->questions->filter(fn (SurveyQuestion $q) => $q->type !== 'text')->values();
        $inputs = trim((string) $request->input('text')) === '' ? [] : explode('*', (string) $request->input('text'));
        $answers = [];
        $index = 0;
        $invalid = false;

        foreach ($inputs as $input) {
            $question = $questions[$index] ?? null;
            if (! $question) {
                break;
            }
            $keys = array_keys($question->choices());
            $picked = $keys[(int) $input - 1] ?? null;

            if (! ctype_digit(trim($input)) || $picked === null) {
                $invalid = true;

                continue;
            }

            $invalid = false;
            $answers[$question->id] = $question->type === 'multiple' ? [$picked] : $picked;
            $index++;
        }

        if ($index >= $questions->count()) {
            try {
                $recorder->record($survey, $answers, ['uuid' => (string) Str::uuid(), 'channel' => 'ussd', 'phone' => $phone]);
            } catch (ValidationException) {
                return $this->end('We could not save your answers. Please try again later.');
            }

            return $this->end('Thank you! Your answers are saved.');
        }

        return $this->con($this->screen($questions[$index], $invalid, $index === 0 ? $survey->title : null));
    }

    public function sms(Request $request, string $token, SurveyRecorder $recorder): Response
    {
        abort_unless(SurveyPoll::valid($token), 404);

        [$keyword, $answer] = array_pad(preg_split('/\s+/', trim((string) $request->input('text')), 2) ?: [], 2, '');
        $survey = Survey::query()->with('questions')->where('status', Survey::LIVE)->where('sms_keyword', strtoupper($keyword))->first();
        $question = $survey?->questions->first(fn (SurveyQuestion $q) => $q->type !== 'text');

        if ($survey && $question && $survey->hasChannel('sms')) {
            $keys = array_keys($question->choices());
            $picked = $keys[(int) trim($answer) - 1] ?? null;

            if ($picked !== null) {
                try {
                    $recorder->record($survey, [$question->id => $question->type === 'multiple' ? [$picked] : $picked], ['uuid' => (string) Str::uuid(), 'channel' => 'sms', 'phone' => (string) $request->input('from')]);
                } catch (ValidationException) {
                    // A second answer from the same phone, or a closed poll: ignored.
                }
            }
        }

        return response('', 200);
    }

    private function screen(SurveyQuestion $question, bool $invalid, ?string $title): string
    {
        $lines = [];
        foreach (array_values($question->choices()) as $i => $label) {
            $lines[] = ($i + 1).'. '.$label;
        }

        $head = ($invalid ? "Choose a number from the list.\n" : '').($title ? Str::limit($title, 40)."\n" : '').$question->prompt;
        $text = $head."\n".implode("\n", $lines);

        // Too long for a USSD screen: shorten the choices, then the question.
        if (mb_strlen($text) > self::SCREEN) {
            $lines = array_map(fn ($line) => Str::limit($line, 22, ''), $lines);
            $text = Str::limit($head, self::SCREEN - mb_strlen(implode("\n", $lines)) - 2, '…')."\n".implode("\n", $lines);
        }

        return mb_substr($text, 0, self::SCREEN);
    }

    private function con(string $text): Response
    {
        return response('CON '.$text, 200, ['Content-Type' => 'text/plain']);
    }

    private function end(string $text): Response
    {
        return response('END '.$text, 200, ['Content-Type' => 'text/plain']);
    }
}
