<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\Segment;
use App\Services\Segments;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * The segment explorer: pick filters, see the count and the breakdowns,
 * save the segment for messaging and broadcasts.
 */
class SegmentController extends Controller
{
    public function index(Request $request, Segments $segments): View
    {
        $filters = $segments->clean($request->query());

        return view('segments.index', [
            'filters' => $filters,
            'label' => $segments->label($filters),
            'result' => $segments->describe($request->user(), $filters),
            'lgas' => Lga::query()->visibleTo($request->user())->orderBy('name')->get(),
            'saved' => Segment::query()->with('author')->latest()->limit(20)->get(),
            'canMessage' => Route::has('messages.create') && in_array($request->user()->role->value, ['admin', 'strategist'], true),
        ]);
    }

    public function store(Request $request, Segments $segments): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $segment = Segment::create(['name' => $data['name'], 'filters' => $segments->clean($request->input('filters', [])), 'created_by' => $request->user()->id]);
        Audit::record('segments.create', "Saved the segment “{$segment->name}”", ['segment_id' => $segment->id]);

        return redirect()->route('segments', $segment->filters)->with('status', "Segment “{$segment->name}” saved.");
    }

    public function destroy(Segment $segment): RedirectResponse
    {
        $segment->delete();

        return back()->with('status', 'Segment deleted.');
    }
}
