<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived caching for the heavy aggregates (zones, segment counts,
 * ward health, leaderboards) that scan hundreds of thousands of voters.
 * Keyed by what the viewer may see, so two people with the same area share
 * the result and nobody sees another area's figures. Anything that changes
 * how figures are worked out (settings, presets, past results) calls
 * flush(). Off (0 seconds) in tests.
 */
class Aggregates
{
    public static function remember(string $name, ?User $viewer, array $params, Closure $compute): mixed
    {
        $seconds = (int) config('campaign.aggregate_cache_seconds');
        if ($seconds <= 0) {
            return $compute();
        }

        $key = 'agg:'.self::version().':'.$name.':'.self::scope($viewer).':'.md5(serialize($params));
        $stored = Cache::get($key);

        if (is_array($stored) && array_key_exists('v', $stored)) {
            return self::hydrate($stored['v']);
        }

        $value = $compute();
        Cache::put($key, ['v' => self::dehydrate($value)], $seconds);

        return $value;
    }

    /**
     * Plain data only goes into the cache (Laravel won't unserialize
     * objects from it, which protects against gadget chains): models become
     * class + id references, collections and dates get markers.
     */
    private static function dehydrate(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Model => ['__m' => $value::class, 'id' => $value->getKey()],
            $value instanceof Collection => ['__c' => array_map(self::dehydrate(...), $value->all())],
            $value instanceof CarbonInterface => ['__d' => $value->toIso8601String()],
            is_array($value) => array_map(self::dehydrate(...), $value),
            is_object($value) => throw new \LogicException('Aggregates can only cache plain data, models, collections and dates.'),
            default => $value,
        };
    }

    private static function hydrate(mixed $value): mixed
    {
        $models = [];
        self::collect($value, $models);
        $loaded = [];
        foreach ($models as $class => $ids) {
            $loaded[$class] = $class::query()->whereKey(array_keys($ids))->get()->keyBy(fn ($model) => $model->getKey())->all();
        }

        return self::rebuild($value, $loaded);
    }

    private static function collect(mixed $value, array &$models): void
    {
        if (! is_array($value)) {
            return;
        }
        if (isset($value['__m'])) {
            $models[$value['__m']][$value['id']] = true;

            return;
        }
        foreach ($value as $item) {
            self::collect($item, $models);
        }
    }

    private static function rebuild(mixed $value, array $loaded): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return match (true) {
            isset($value['__m']) => $loaded[$value['__m']][$value['id']] ?? null,
            isset($value['__c']) && count($value) === 1 => collect(array_map(fn ($item) => self::rebuild($item, $loaded), $value['__c'])),
            isset($value['__d']) && count($value) === 1 => Carbon::parse($value['__d']),
            default => array_map(fn ($item) => self::rebuild($item, $loaded), $value),
        };
    }

    /** Invalidate every cached aggregate (a new version prefix). */
    public static function flush(): void
    {
        Cache::forever('agg:version', self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::get('agg:version', 1);
    }

    private static function scope(?User $viewer): string
    {
        return match (true) {
            $viewer === null || $viewer->role->isStatewide() => 'all',
            $viewer->role === UserRole::LgaLeader && $viewer->lga_id !== null => 'lga'.$viewer->lga_id,
            $viewer->ward_id !== null => 'ward'.$viewer->ward_id,
            default => 'none',
        };
    }
}
