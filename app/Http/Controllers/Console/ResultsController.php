<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\PastResult;
use App\Services\Intelligence;
use App\Services\PastResultImporter;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Past governorship results, supplied by the campaign as CSV
 * (lga,ward,party,votes). Nothing here is invented: with no file, zones
 * rest on canvassing and surveys.
 */
class ResultsController extends Controller
{
    public function index(): View
    {
        $years = PastResult::query()->selectRaw('year, count(*) as n, sum(votes) as votes, count(distinct ward_id) as wards')->groupBy('year')->orderByDesc('year')->get();
        $party = Intelligence::party();
        $latest = $years->first()?->year;

        $byLga = $latest ? PastResult::query()->where('year', $latest)->with('lga')->get()->groupBy('lga_id')->map(function ($rows) use ($party) {
            $scope = $rows->whereNull('ward_id')->isNotEmpty() ? $rows->whereNull('ward_id') : $rows;
            $total = $scope->sum('votes');
            $parties = $scope->groupBy('party')->map->sum('votes')->sortDesc();

            return ['lga' => $rows->first()->lga, 'total' => $total, 'parties' => $parties, 'ours' => $party && $total ? round(100 * ($parties[$party] ?? 0) / $total, 1) : null];
        })->sortBy(fn ($row) => $row['lga']->name)->values() : collect();

        return view('results.index', ['years' => $years, 'byLga' => $byLga, 'latest' => $latest, 'party' => $party]);
    }

    public function import(Request $request, PastResultImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1999', 'max:2027'],
            'file' => ['required', 'file', 'max:8192', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
        ]);

        try {
            $result = $importer->import($request->file('file')->getRealPath(), (int) $data['year']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        Audit::record('results.import', "Imported {$data['year']} results: {$result['rows']} rows".($result['errors'] ? ', '.count($result['errors']).' skipped' : ''), rows: $result['rows']);

        return back()->with($result['errors'] ? 'error' : 'status', "{$data['year']}: {$result['rows']} rows imported.".($result['errors'] ? ' Skipped: '.implode('; ', array_slice($result['errors'], 0, 5)) : ''));
    }

    public function destroy(Request $request, int $year): RedirectResponse
    {
        $rows = PastResult::query()->where('year', $year)->delete();
        Audit::record('results.delete', "Deleted the {$year} results", rows: $rows);

        return back()->with('status', "The {$year} results are removed.");
    }
}
