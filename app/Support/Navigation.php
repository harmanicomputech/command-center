<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The Command Center navigation: sidebar sections, the phone tab bar, the
 * "More" sheet and the command palette all come from here. An item shows
 * when its route exists (later phases add routes) and the user's role is
 * allowed; the server still checks access on every route.
 */
class Navigation
{
    /**
     * [section => [[label, route, icon, active route patterns, roles or null for all staff]]]
     *
     * @return array<string, list<array{0: string, 1: string, 2: string, 3: list<string>, 4: ?list<UserRole>}>>
     */
    private static function definition(): array
    {
        $admin = [UserRole::Admin];
        $leaders = [UserRole::Admin, UserRole::Strategist, UserRole::LgaLeader];
        $analysts = [UserRole::Admin, UserRole::Strategist];

        return [
            'Overview' => [
                ['Dashboard', 'dashboard', 'layout-dashboard', ['dashboard'], null],
                ['Daily brief', 'brief', 'file-text', ['brief'], $leaders],
            ],
            'Intelligence' => [
                ['Map & wards', 'areas', 'map', ['areas', 'areas.*'], null],
                ['Segments', 'segments', 'chart-pie', ['segments', 'segments.*'], $leaders],
                ['Surveys', 'surveys', 'clipboard-list', ['surveys', 'surveys.*'], $leaders],
                ['Influence', 'influencers', 'landmark', ['influencers'], null],
                ['Past results', 'results', 'vote', ['results', 'results.*'], $analysts],
            ],
            'Field' => [
                ['People', 'people', 'users', ['people', 'people.*'], null],
                ['Structure health', 'structure', 'heart-pulse', ['structure'], null],
                ['Volunteers', 'volunteers', 'hand-heart', ['volunteers'], null],
                ['Team & invites', 'team', 'user-plus', ['team'], [UserRole::Admin, UserRole::LgaLeader, UserRole::WardCoordinator]],
                ['Registrations', 'voters', 'user-round-check', ['voters', 'voters.*'], null],
                ['Tasks', 'tasks', 'list-todo', ['tasks', 'tasks.*'], null],
                ['Issues', 'issues', 'triangle-alert', ['issues', 'issues.*'], null],
                ['Events', 'events', 'calendar', ['events', 'events.*'], null],
                ['Leaderboard', 'leaderboard', 'trophy', ['leaderboard'], null],
            ],
            'Engage' => [
                ['Messages', 'messages', 'sparkles', ['messages', 'messages.*'], $analysts],
                ['Broadcasts', 'broadcasts', 'megaphone', ['broadcasts', 'broadcasts.*'], $analysts],
                ['Narratives', 'narratives', 'radio', ['narratives', 'narratives.*'], $leaders],
                ['Complaints', 'complaints', 'message-square', ['complaints'], $leaders],
                ['News', 'news', 'newspaper', ['news', 'news.*'], $analysts],
                ['Our pages', 'posts', 'thumbs-up', ['posts'], $analysts],
                ['Policy brief', 'policies', 'book-open', ['policies'], $analysts],
            ],
            'Admin' => [
                ['Users', 'users', 'user-plus', ['users', 'users.*'], $admin],
                ['Settings', 'settings', 'sliders-horizontal', ['settings'], $admin],
                ['System', 'system', 'server', ['system', 'system.*'], $admin],
                ['Audit log', 'audit', 'scroll-text', ['audit'], $admin],
                ['Data requests', 'data-requests', 'shield-check', ['data-requests'], $admin],
                ['Design system', 'design', 'palette', ['design'], $admin],
            ],
        ];
    }

    /**
     * @return array<string, list<array{label: string, route: string, icon: string, active: list<string>}>>
     */
    public static function sections(User $user): array
    {
        $sections = [];

        foreach (self::definition() as $section => $items) {
            $visible = [];

            foreach ($items as [$label, $route, $icon, $active, $roles]) {
                if (Route::has($route) && ($roles === null || in_array($user->role, $roles, true))) {
                    $visible[] = ['label' => $label, 'route' => $route, 'icon' => $icon, 'active' => $active];
                }
            }

            if ($visible !== []) {
                $sections[$section] = $visible;
            }
        }

        return $sections;
    }

    /**
     * The phone tab bar for Command Center users: four places and More.
     *
     * @return list<array{label: string, route: string, icon: string, active: list<string>}>
     */
    public static function tabs(User $user): array
    {
        $all = collect(self::sections($user))->flatten(1)->keyBy('route');

        return collect([
            ['dashboard', 'Home'],
            ['areas', 'Map'],
            ['voters', 'Field'],
            ['team', 'Team'],
            ['messages', 'Engage'],
            ['users', 'Users'],
            ['system', 'System'],
        ])->filter(fn ($tab) => $all->has($tab[0]))
            ->map(fn ($tab) => [...$all[$tab[0]], 'label' => $tab[1]])
            ->take(4)->values()->all();
    }

    /**
     * Pages for the command palette.
     *
     * @return list<array{label: string, url: string, icon: string, group: string}>
     */
    public static function commands(User $user): array
    {
        $commands = [];

        foreach (self::sections($user) as $section => $items) {
            foreach ($items as $item) {
                $commands[] = ['label' => $item['label'], 'url' => route($item['route']), 'icon' => Icons::paths($item['icon']), 'group' => $section];
            }
        }

        $commands[] = ['label' => 'My account', 'url' => route('account'), 'icon' => Icons::paths('circle-user'), 'group' => 'You'];

        return $commands;
    }
}
