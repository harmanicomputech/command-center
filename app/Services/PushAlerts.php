<?php

namespace App\Services;

use Throwable;

/**
 * Alerts queued during a request or command and sent once it is over, so
 * the user never waits for push services. A static list flushed in
 * app()->terminating(), registered once per application instance (Election
 * Shield sent duplicates with dispatch()->afterResponse()).
 */
class PushAlerts
{
    /** @var list<array{topic: string, message: array<string, mixed>, lga: ?int, users: ?list<int>}> */
    private static array $pending = [];

    private static ?int $registeredFor = null;

    /**
     * @param  array{title: string, body: string, url: string, tag?: string, urgent?: bool}  $message
     * @param  list<int>|null  $userIds
     */
    public static function queue(string $topic, array $message, ?int $lgaId = null, ?array $userIds = null): void
    {
        if ($userIds === []) {
            return;
        }

        self::$pending[] = ['topic' => $topic, 'message' => $message, 'lga' => $lgaId, 'users' => $userIds];

        $app = app();
        if (self::$registeredFor !== spl_object_id($app)) {
            self::$registeredFor = spl_object_id($app);
            $app->terminating(fn () => self::flush());
        }
    }

    public static function flush(): int
    {
        $pending = self::$pending;
        self::$pending = [];
        $sent = 0;

        foreach ($pending as $alert) {
            try {
                $sent += app(PushNotifier::class)->toTopic($alert['topic'], $alert['message'], $alert['lga'], $alert['users']);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    /** @return list<array{topic: string, message: array<string, mixed>, lga: ?int, users: ?list<int>}> */
    public static function pending(): array
    {
        return self::$pending;
    }

    public static function reset(): void
    {
        self::$pending = [];
        self::$registeredFor = null;
    }
}
