<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $action = (string) $request->query('action');

        return view('audit.index', [
            'logs' => AuditLog::query()
                ->when($action !== '', fn ($query) => $query->where('action', 'like', $action.'%'))
                ->latest('id')->paginate(50)->withQueryString(),
            'action' => $action,
            'groups' => AuditLog::query()->selectRaw("substr(action, 1, instr(action, '.') - 1) as area")->distinct()->pluck('area')->filter()->sort()->values(),
        ]);
    }
}
