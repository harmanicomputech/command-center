<?php

namespace App\Console\Commands;

use App\Services\NewsTracker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('news:fetch')]
#[Description('Fetch the tracked news feeds and flag keyword matches')]
class FetchNews extends Command
{
    public function handle(NewsTracker $tracker): void
    {
        $totals = $tracker->fetchAll();
        $this->line("{$totals['feeds']} feeds, {$totals['new']} new stories, {$totals['alerts']} keyword alerts.");
    }
}
