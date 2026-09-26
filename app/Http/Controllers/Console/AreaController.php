<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Influencer;
use App\Models\Issue;
use App\Models\Lga;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Services\Intelligence;
use App\Services\MapLayers;
use App\Services\Structure;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LGAs → wards → polling units, always within the user's area: an LGA
 * leader who opens another LGA's URL gets a 403.
 */
class AreaController extends Controller
{
    public function index(Request $request, Intelligence $intel, MapLayers $layers): View
    {
        $user = $request->user();
        $zone = array_key_exists((string) $request->query('zone'), Intelligence::ZONES) ? $request->query('zone') : null;
        $lga = $request->filled('lga') ? Lga::query()->visibleTo($user)->where('slug', $request->query('lga'))->first() : null;
        $sort = in_array($request->query('sort'), ['priority', 'share', 'registered', 'canvassed', 'name'], true) ? $request->query('sort') : 'priority';

        $all = $intel->wards($user, $lga?->id);
        $wards = $all->when($zone, fn ($rows) => $rows->where('zone', $zone))
            ->sortBy(fn ($row) => $sort === 'name' ? $row['name'] : -($row[$sort] ?? -1))->values();

        return view('areas.index', [
            'wards' => $wards,
            'zoneCounts' => $all->countBy('zone'),
            'zone' => $zone,
            'lga' => $lga,
            'sort' => $sort,
            'lgas' => Lga::query()->visibleTo($user)->orderBy('name')->get(),
            'map' => $layers->build($user),
            'lgaRows' => $intel->lgas($user),
            'party' => Intelligence::party(),
        ]);
    }

    public function lga(Request $request, Lga $lga): View
    {
        abort_unless($request->user()->canSeeLga($lga), 403, 'This LGA is outside your area.');

        $wards = Ward::query()->where('lga_id', $lga->id)->visibleTo($request->user())->orderBy('name')->get();
        $team = User::query()->whereIn('ward_id', $wards->pluck('id'))->whereIn('role', [UserRole::WardCoordinator, UserRole::Agent])
            ->selectRaw('ward_id, role, count(*) as n')->groupBy('ward_id', 'role')->get();

        return view('areas.lga', [
            'lga' => $lga,
            'wards' => $wards,
            'maxRegistered' => max(1, (int) $wards->max('registered_voters')),
            'coordinators' => $team->where('role', UserRole::WardCoordinator)->pluck('n', 'ward_id'),
            'agents' => $team->where('role', UserRole::Agent)->pluck('n', 'ward_id'),
            'leaders' => User::query()->where('role', UserRole::LgaLeader)->where('lga_id', $lga->id)->orderBy('name')->get(),
        ]);
    }

    public function ward(Request $request, Lga $lga, string $ward, Structure $structure, Intelligence $intel): View
    {
        $ward = Ward::query()->where('lga_id', $lga->id)->where('slug', $ward)->firstOrFail();
        abort_unless($request->user()->canSeeWard($ward), 403, 'This ward is outside your area.');
        $team = User::query()->where('ward_id', $ward->id)->whereNull('disabled_at')->orderByRaw("case role when 'ward_coordinator' then 0 else 1 end")->orderBy('name')->get();

        return view('areas.ward', [
            'lga' => $lga,
            'ward' => $ward,
            'units' => $ward->pollingUnits()->get(),
            'team' => $team,
            'engagement' => $structure->engagement($team),
            'health' => $structure->wardHealth($request->user(), $lga->id)->firstWhere(fn ($row) => $row['ward']->id === $ward->id),
            'influencers' => Influencer::query()->where('ward_id', $ward->id)->orderBy('name')->get(),
            'events' => Event::query()->where('ward_id', $ward->id)->where('starts_at', '>=', now()->subDays(30))->orderBy('starts_at')->limit(6)->get(),
            'registrations' => Voter::query()->counted()->where('ward_id', $ward->id)->count(),
            'wardOptions' => [$ward->id => $ward->fullName()],
            'intel' => $intel->wards()->firstWhere('id', $ward->id),
            'segments' => [
                'support' => Voter::query()->counted()->where('ward_id', $ward->id)->selectRaw('support_level as k, count(*) as n')->groupBy('support_level')->pluck('n', 'k'),
                'age' => Voter::query()->counted()->where('ward_id', $ward->id)->selectRaw('age_band as k, count(*) as n')->groupBy('age_band')->pluck('n', 'k'),
                'occupation' => Voter::query()->counted()->where('ward_id', $ward->id)->selectRaw('occupation as k, count(*) as n')->groupBy('occupation')->pluck('n', 'k'),
            ],
            'issues' => Issue::query()->where('ward_id', $ward->id)->where('status', '!=', 'rejected')->selectRaw('category, count(*) as n')->groupBy('category')->orderByDesc('n')->limit(5)->pluck('n', 'category'),
        ]);
    }
}
