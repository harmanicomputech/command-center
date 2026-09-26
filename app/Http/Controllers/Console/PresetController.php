<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Services\Intelligence;
use App\Support\Aggregates;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LGA presets the strategists edit: reachability (how easily the campaign
 * can reach voters there, 10–100%) and a label such as "Priority
 * mobilisation" or "Urban: digital and media". Starting presets come from
 * the brief; none of them are hard-coded rules.
 */
class PresetController extends Controller
{
    public function index(): View
    {
        return view('results.presets', ['lgas' => Lga::query()->orderBy('name')->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Zones, points and targets may change: recompute cached figures.
        Aggregates::flush();

        $data = $request->validate([
            'reach' => ['array'],
            'reach.*' => ['integer', 'min:10', 'max:100'],
            'tag' => ['array'],
            'tag.*' => ['nullable', 'string', 'max:60'],
        ]);

        foreach (Lga::query()->get() as $lga) {
            if (isset($data['reach'][$lga->slug])) {
                Settings::set("intel.reach.{$lga->slug}", (string) ($data['reach'][$lga->slug] / 100));
            }
            if (array_key_exists($lga->slug, $data['tag'] ?? [])) {
                Settings::set("intel.tag.{$lga->slug}", trim((string) $data['tag'][$lga->slug]));
            }
        }

        Audit::record('presets.update', 'Changed the LGA presets');

        return back()->with('status', 'Presets saved. Priorities are recalculated.');
    }

    public static function reachPercent(string $slug): int
    {
        return (int) round(100 * Intelligence::reach($slug));
    }
}
