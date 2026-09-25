<?php

namespace App\Http\Controllers;

use App\Support\Settings;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Field Force app (phone). Registration, tasks and issues arrive in
 * phases 2 and 4; the shell, home and profile are here from phase 1.
 */
class FieldController extends Controller
{
    public function home(Request $request): View
    {
        return view('field.home', [
            'user' => $request->user(),
            'greeting' => Time::greeting(),
            'today' => 0,
            'target' => Settings::int('target.agent_daily'),
            'streak' => 0,
            'rank' => null,
        ]);
    }

    public function me(Request $request): View
    {
        return view('field.me', ['user' => $request->user()]);
    }

    public function register(): View
    {
        return view('field.soon', ['page' => 'register']);
    }

    public function tasks(): View
    {
        return view('field.soon', ['page' => 'tasks']);
    }

    public function issues(): View
    {
        return view('field.soon', ['page' => 'issues']);
    }
}
