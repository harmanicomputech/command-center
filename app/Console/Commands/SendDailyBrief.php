<?php

namespace App\Console\Commands;

use App\Services\DailyBrief;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('brief:notify')]
#[Description('Send the 7 AM "daily brief is ready" and "wards went quiet" alerts')]
class SendDailyBrief extends Command
{
    public function handle(DailyBrief $brief): void
    {
        $brief->notify();
        $this->line('Daily brief alerts sent.');
    }
}
