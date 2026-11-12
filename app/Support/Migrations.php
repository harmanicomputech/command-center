<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Database updates uploaded but not yet run. After an upload the new code
 * may ask for columns that don't exist until an admin presses Update
 * database, so pages that would fail send admins to that button instead
 * (bootstrap/app.php, SystemController).
 */
class Migrations
{
    private static ?array $pending = null;

    /**
     * @return list<string>
     */
    public static function pending(): array
    {
        if (self::$pending !== null) {
            return self::$pending;
        }

        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
        } catch (Throwable) {
            return self::$pending = ['(no migrations table)'];
        }

        $files = array_map(fn (string $path) => basename($path, '.php'), glob(database_path('migrations/*.php')) ?: []);

        return self::$pending = array_values(array_diff($files, $ran));
    }

    public static function forget(): void
    {
        self::$pending = null;
    }
}
