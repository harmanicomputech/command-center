<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Services\Structure;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Structure health per ward: coordinator, active agents, last meeting,
 * last activity. Red wards first.
 */
class StructureController extends Controller
{
    public function index(Request $request, Structure $structure): View
    {
        $user = $request->user();
        $lga = $request->filled('lga') ? Lga::query()->visibleTo($user)->where('slug', $request->query('lga'))->first() : null;
        $health = $structure->wardHealth($user, $lga?->id)->sortBy(fn ($row) => [$row['red'] ? 0 : 1, $row['ward']->lga->name, $row['ward']->name])->values();

        return view('structure.index', [
            'health' => $health,
            'lgas' => Lga::query()->visibleTo($user)->orderBy('name')->get(),
            'lga' => $lga,
            'summary' => [
                'wards' => $health->count(),
                'red' => $health->where('red', true)->count(),
                'noCoordinator' => $health->where('coordinators', 0)->count(),
                'agents' => $health->sum('agents'),
                'active' => $health->sum('active'),
            ],
        ]);
    }
}
