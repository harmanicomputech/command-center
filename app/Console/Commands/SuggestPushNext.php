<?php

namespace App\Console\Commands;

use App\Services\Ai\AiException;
use App\Services\Ai\PushNextSuggester;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:suggest')]
#[Description('Write the daily AI "what message to push next" suggestion for the dashboard')]
class SuggestPushNext extends Command
{
    public function handle(PushNextSuggester $suggester): int
    {
        @set_time_limit(240);

        try {
            $text = $suggester->run();
        } catch (AiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($text ?? 'AI is not set up: no suggestion written.');

        return self::SUCCESS;
    }
}
