<?php

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * The web app manifest, served from a route so the name follows the
 * Settings page. The theme colour is the brand token.
 */
class PwaController extends Controller
{
    public const THEME_COLOR = '#0f6e4f';

    public function manifest(): JsonResponse
    {
        $name = Settings::get('campaign.name') ?: config('app.name');

        return response()->json([
            'name' => $name,
            'short_name' => mb_strimwidth($name, 0, 14, ''),
            'description' => 'Campaign command center and field force app for the Ebonyi State governorship election.',
            'id' => '/',
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f7f6f3',
            'theme_color' => self::THEME_COLOR,
            'categories' => ['productivity', 'utilities'],
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Register voter', 'short_name' => 'Register', 'url' => '/field/register?source=shortcut', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Report issue', 'short_name' => 'Issue', 'url' => '/field/issues?source=shortcut', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'My tasks', 'short_name' => 'Tasks', 'url' => '/field/tasks?source=shortcut', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES);
    }
}
