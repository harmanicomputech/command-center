<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\User;
use App\Models\Ward;
use App\Services\LgaMap;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LGAs → wards → polling units, always within the user's area: an LGA
 * leader who opens another LGA's URL gets a 403.
 */
class AreaController extends Controller
{
    public function index(Request $request, LgaMap $map): View
    {
        $user = $request->user();
        $lgas = Lga::query()->visibleTo($user)->orderBy('name')->get();
        $all = Lga::query()->get();
        $coordinators = $this->coordinatorsPerLga();

        return view('areas.index', [
            'lgas' => $lgas,
            'coordinators' => $coordinators,
            'maxRegistered' => max(1, (int) $lgas->max('registered_voters')),
            'map' => $map->build($user, [
                'registered' => ['label' => 'Registered voters', 'values' => $all->pluck('registered_voters', 'name')->all(), 'format' => 'compact'],
                'wards' => ['label' => 'Wards', 'values' => $all->pluck('wards_count', 'name')->all()],
            ]),
            'totals' => [
                'registered' => (int) $lgas->sum('registered_voters'),
                'wards' => (int) $lgas->sum('wards_count'),
                'units' => (int) $lgas->sum('polling_units_count'),
            ],
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

    public function ward(Request $request, Lga $lga, string $ward): View
    {
        $ward = Ward::query()->where('lga_id', $lga->id)->where('slug', $ward)->firstOrFail();
        abort_unless($request->user()->canSeeWard($ward), 403, 'This ward is outside your area.');

        return view('areas.ward', [
            'lga' => $lga,
            'ward' => $ward,
            'units' => $ward->pollingUnits()->get(),
            'team' => User::query()->where('ward_id', $ward->id)->orderByRaw("case role when 'ward_coordinator' then 0 else 1 end")->orderBy('name')->get(),
        ]);
    }

    /**
     * @return array<int, int> LGA id → wards with a coordinator
     */
    private function coordinatorsPerLga(): array
    {
        return User::query()->where('role', UserRole::WardCoordinator)->whereNotNull('ward_id')
            ->join('wards', 'wards.id', '=', 'users.ward_id')
            ->selectRaw('wards.lga_id, count(distinct users.ward_id) as n')->groupBy('wards.lga_id')
            ->pluck('n', 'wards.lga_id')->map(fn ($n) => (int) $n)->all();
    }
}
