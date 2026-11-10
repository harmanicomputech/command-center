<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Ward;
use App\Services\WardNames;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admins give wards their real names: one on its page, or all from a CSV.
 */
class WardNameController extends Controller
{
    public function update(Request $request, Ward $ward, WardNames $names): RedirectResponse
    {
        $request->validate(['name' => ['nullable', 'string', 'max:120']]);
        $old = $ward->name;

        if ($names->rename($ward, $request->input('name'))) {
            Audit::record('wards.rename', "Renamed ward {$old} to {$ward->name}", ['ward_id' => $ward->id]);

            return back()->with('status', "The ward is now called {$ward->name}.");
        }

        return back()->with('status', 'Nothing changed.');
    }

    public function export(WardNames $names): StreamedResponse
    {
        return response()->streamDownload(function () use ($names) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // so Excel reads the names as UTF-8
            fputcsv($out, WardNames::COLUMNS, escape: '\\');
            foreach ($names->rows() as $row) {
                fputcsv($out, $row, escape: '\\');
            }
            fclose($out);
        }, 'ward-names.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, WardNames $names): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel']]);
        $result = $names->import($request->file('file')->getRealPath());

        Audit::record('wards.rename', "Imported ward names: {$result['renamed']} renamed", rows: $result['renamed']);
        $message = "Ward names: {$result['renamed']} renamed, {$result['unchanged']} unchanged.";

        return redirect()->to(route('system').'#ward-names')->with(
            $result['errors'] ? 'error' : 'status',
            $message.($result['errors'] ? ' Some rows were skipped: '.implode('; ', array_slice($result['errors'], 0, 5)) : '')
        );
    }
}
