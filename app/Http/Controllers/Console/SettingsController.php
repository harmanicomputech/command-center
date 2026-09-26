<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Aggregates;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The owner's decisions that have placeholders (names, targets, weights,
 * points) are edited here, one group at a time.
 */
class SettingsController extends Controller
{
    public function show(): View
    {
        return view('settings.show', ['groups' => SettingsRegistry::groups()]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        // Zones, points and targets may change: recompute cached figures.
        Aggregates::flush();

        $definition = SettingsRegistry::groups()[$group] ?? abort(404);
        $rules = [];

        foreach ($definition['fields'] as $key => $field) {
            $rules[$this->input($key)] = match ($field['type']) {
                'int' => ['required', 'integer', 'min:'.($field['min'] ?? 0), 'max:'.($field['max'] ?? PHP_INT_MAX)],
                'number' => ['required', 'numeric', 'min:'.($field['min'] ?? 0), 'max:'.($field['max'] ?? PHP_INT_MAX)],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $validated = $request->validate($rules);
        $changed = [];

        foreach ($definition['fields'] as $key => $field) {
            $value = trim((string) ($validated[$this->input($key)] ?? ''));

            if ($value !== (string) Settings::get($key)) {
                Settings::set($key, $value);
                $changed[] = $field['label'];
            }
        }

        if ($changed !== []) {
            Audit::record('settings.update', "Changed {$definition['title']} settings: ".implode(', ', $changed));
        }

        return redirect()->route('settings')->with('status', $changed ? "{$definition['title']} saved." : 'Nothing changed.');
    }

    /** "target.agent_daily" → "target__agent_daily" (dots would nest). */
    private function input(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
