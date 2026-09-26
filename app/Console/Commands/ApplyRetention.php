<?php

namespace App\Console\Commands;

use App\Services\Erasure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('privacy:retention')]
#[Description('After the retention date, erase a batch of voter personal data (runs until none is left)')]
class ApplyRetention extends Command
{
    public function handle(Erasure $erasure): void
    {
        $date = Erasure::retentionDate();
        $erased = $erasure->applyRetention();
        $this->line($date ? "Retention date {$date->toDateString()}: erased {$erased} records in this batch." : 'Retention is switched off.');
    }
}
