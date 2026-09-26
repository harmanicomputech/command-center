<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PastResult;
use App\Models\User;
use App\Models\Ward;
use App\Services\DailyBrief;
use App\Services\MapLayers;
use App\Services\PushNotifier;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The daily dashboard (leadership's home page) and its printable brief.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DailyBrief $brief, MapLayers $layers): View
    {
        $user = $request->user();

        return view('dashboard.index', [
            'greeting' => Time::greeting(),
            'daysToGo' => Time::daysToElection(),
            'brief' => $brief->build($user),
            'map' => $layers->build($user),
            'setup' => $user->isAdmin() ? $this->setupSteps() : [],
        ]);
    }

    public function brief(Request $request, DailyBrief $brief, MapLayers $layers): View
    {
        Audit::record('brief.view', 'Opened the daily brief');

        return view('dashboard.brief', ['brief' => $brief->build($request->user()), 'map' => $layers->build($request->user())]);
    }

    /**
     * @return list<array{label: string, done: bool, url: string}>
     */
    private function setupSteps(): array
    {
        return [
            ['label' => 'Load the polling unit register', 'done' => Ward::query()->exists(), 'url' => route('system')],
            ['label' => 'Confirm the register, or upload INEC’s', 'done' => filled(Settings::get('register.confirmed_at')), 'url' => route('system').'#register'],
            ['label' => 'Name the campaign, candidate and party', 'done' => filled(Settings::get('campaign.party')), 'url' => route('settings')],
            ['label' => 'Import past results (2019, 2023)', 'done' => PastResult::query()->exists(), 'url' => route('results')],
            ['label' => 'Set up the background runner (pinger)', 'done' => filled(Settings::get('runner.heartbeat')), 'url' => route('system').'#runner'],
            ['label' => 'Turn on notifications', 'done' => app(PushNotifier::class)->configured(), 'url' => route('system').'#push'],
            ['label' => 'Add LGA leaders and ward coordinators', 'done' => User::query()->whereIn('role', [UserRole::LgaLeader, UserRole::WardCoordinator])->exists(), 'url' => route('users')],
        ];
    }
}
