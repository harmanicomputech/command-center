<?php

namespace App\Support;

use App\Models\User;
use App\Services\DailyBrief;
use App\Services\MapLayers;
use App\Services\Segments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs the background work (periodic tasks and the database queue) without
 * a per-minute cron, which the host forbids. Reused from Election Shield.
 * It is triggered from three places, all safe to overlap:
 *
 *  - after the response of ordinary web requests (RunBackgroundWork), at
 *    most every 15 seconds;
 *  - an external pinger opening /cron/{token} every minute (cron-job.org);
 *  - the host's cron running `php artisan app:tick` (hourly is fine).
 *
 * Periodic tasks remember the slot they last ran for (in settings), so
 * nothing runs twice however often the runner is triggered. Add new
 * periodic tasks in tasks(), not to routes/console.php.
 */
class BackgroundRunner
{
    /** Don't start a run from web requests more often than this. */
    private const MIN_SECONDS_BETWEEN_WEB_RUNS = 15;

    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'command-center-runner', (string) config('app.key')), 0, 32);
    }

    public static function enabled(): bool
    {
        return (bool) config('campaign.background_runner');
    }

    /**
     * Whether PHP can send the response before carrying on (PHP-FPM or
     * LiteSpeed); otherwise the visitor would wait for the work.
     */
    public static function canWorkAfterResponse(): bool
    {
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }

    public function runAfterWebRequest(): void
    {
        if (! self::enabled() || ! Cache::add('command-center:runner-throttle', true, self::MIN_SECONDS_BETWEEN_WEB_RUNS)) {
            return;
        }

        $this->run(queueSeconds: 20, source: 'web');
    }

    /**
     * One pass: due tasks, then the queue for up to $queueSeconds. Only one
     * pass runs at a time; returns false if another is running.
     */
    public function run(int $queueSeconds = 20, string $source = 'cron'): bool
    {
        $lock = Cache::lock('command-center:runner', $queueSeconds + 120);

        if (! $lock->get()) {
            return false;
        }

        try {
            @set_time_limit($queueSeconds + 100);
            ignore_user_abort(true);

            Settings::set('runner.heartbeat', now()->toIso8601String());
            Settings::set('runner.source', $source);

            $this->runDueTasks();

            Artisan::call('queue:work', [
                '--queue' => 'high,default,bulk',
                '--stop-when-empty' => true,
                '--max-time' => $queueSeconds,
                '--tries' => 5,
            ]);
        } catch (Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }

        return true;
    }

    /**
     * The periodic tasks: [name => [slot for now or null when not due, task]].
     * A task runs once per distinct slot.
     *
     * @return array<string, array{0: ?string, 1: callable}>
     */
    protected function tasks(Carbon $now): array
    {
        return [
            // 7 AM Lagos: "Your daily brief is ready" and quiet wards.
            'daily-brief' => [self::dailyAt($now, '07:00'), fn () => Artisan::call('brief:notify')],
            // 6:30 AM Lagos: the AI "what to push next" suggestion, ready for the brief.
            'push-next' => [self::dailyAt($now, '06:30'), fn () => Artisan::call('ai:suggest')],
            // RSS news tracker, with keyword alerts.
            // Keep the statewide dashboard figures warm, so nobody waits for them.
            'warm' => [self::everyMinutes($now, 5), fn () => self::warm()],
            // After the retention date, voter personal data is erased in batches.
            'retention' => [self::everyMinutes($now, 15), fn () => Artisan::call('privacy:retention')],
            'news' => [self::everyMinutes($now, (int) config('messaging.news_every_minutes')), fn () => Artisan::call('news:fetch')],
        ];
    }

    /** Compute the statewide dashboard aggregates ahead of the first visit. */
    public static function warm(): void
    {
        $viewer = new User(['role' => 'admin']);
        app(DailyBrief::class)->build($viewer);
        app(MapLayers::class)->build($viewer);
        app(Segments::class)->describe($viewer, []);
    }

    private function runDueTasks(): void
    {
        foreach ($this->tasks(now()) as $name => [$slot, $task]) {
            if ($slot === null || Settings::get("runner.slot.{$name}") === $slot) {
                continue;
            }

            Settings::set("runner.slot.{$name}", $slot);
            $this->attempt($task);
        }
    }

    /** The slot for a task that runs every $minutes minutes. */
    public static function everyMinutes(Carbon $now, int $minutes): string
    {
        $minute = intdiv((int) $now->format('i'), $minutes) * $minutes;

        return $now->format('Y-m-d H:').str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The slot for a daily task at a Lagos time ("07:00"): the date once the
     * time has passed today, null before then.
     */
    public static function dailyAt(Carbon $now, string $time): ?string
    {
        $local = $now->copy()->setTimezone(Time::zone());

        return $local->format('H:i') >= $time ? $local->format('Y-m-d') : null;
    }

    /** The slot for a weekly task: the Monday that starts this Lagos week. */
    public static function weekly(Carbon $now): string
    {
        return $now->copy()->setTimezone(Time::zone())->startOfWeek()->format('Y-m-d');
    }

    private function attempt(callable $task): void
    {
        try {
            $task();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
