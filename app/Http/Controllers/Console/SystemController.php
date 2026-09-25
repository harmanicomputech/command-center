<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PollingUnitImporter;
use App\Services\RegisterStatus;
use App\Support\Audit;
use App\Support\BackgroundRunner;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Operator tasks that would otherwise need a terminal: updating the
 * database, the register, and the background runner.
 */
class SystemController extends Controller
{
    public function show(RegisterStatus $register): View
    {
        $heartbeat = Time::parse(Settings::get('runner.heartbeat'));

        return view('system.show', [
            'register' => $register->summary(),
            'heartbeat' => $heartbeat,
            'runnerHealthy' => $heartbeat && $heartbeat->gt(now()->subMinutes(10)),
            'runnerSource' => ['web' => 'after a page visit', 'pinger' => 'by the pinger', 'cron' => 'by the host cron'][Settings::get('runner.source')] ?? null,
            'runnerUrl' => route('runner', BackgroundRunner::token()),
            'afterResponse' => BackgroundRunner::canWorkAfterResponse(),
            'pendingMigrations' => $this->pendingMigrations(),
            'queue' => $this->queueCounts(),
            'counts' => [
                'Users' => User::query()->count(),
                'Audit entries' => AuditLog::query()->count(),
            ],
            'environment' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Database' => DB::connection()->getDriverName(),
                'Timezone shown' => Time::zone(),
            ],
        ]);
    }

    /**
     * Run pending migrations after uploading a new version.
     */
    public function migrate(): RedirectResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Could not update the database: '.$e->getMessage());
        }

        Audit::record('system.migrate', 'Updated the database');

        return back()->with('status', 'The database is up to date.');
    }

    /**
     * Import the PU register: the bundled file, or an uploaded CSV. An upload
     * marked as INEC's official register also confirms it.
     */
    public function importRegister(Request $request, PollingUnitImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:8192', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
            'official' => ['nullable', 'boolean'],
        ]);
        $upload = $request->file('file');
        @set_time_limit(180);

        try {
            $result = $importer->import($upload ? $upload->getRealPath() : PollingUnitImporter::bundledPath());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $source = $upload ? $upload->getClientOriginalName() : 'the bundled Ebonyi register';
        Settings::set('register.source', $source);

        if ($upload && $request->boolean('official') && $result['errors'] === []) {
            $this->markConfirmed($request, "INEC register ({$source})");
        }

        Audit::record('system.register_import', "Imported the register from {$source}: {$result['created']} added, {$result['updated']} updated".($result['errors'] ? ', '.count($result['errors']).' rows skipped' : ''), rows: $result['created'] + $result['updated']);

        return back()->with(
            $result['errors'] ? 'error' : 'status',
            "Register from {$source}: {$result['created']} polling units added, {$result['updated']} updated.".($result['errors'] ? ' Some rows were skipped: '.implode('; ', array_slice($result['errors'], 0, 5)) : '')
        );
    }

    /**
     * An admin confirms the register's figures are right to use.
     */
    public function confirmRegister(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the box to confirm you have checked the figures.']);
        $this->markConfirmed($request, Settings::get('register.source') ?? 'the bundled register');

        return back()->with('status', 'Register confirmed. The warning is gone.');
    }

    private function markConfirmed(Request $request, string $what): void
    {
        Settings::set('register.confirmed_at', now()->toIso8601String());
        Settings::set('register.confirmed_by', $request->user()->name);
        Audit::record('system.register_confirm', "Confirmed the register: {$what}");
    }

    /**
     * @return list<string>
     */
    private function pendingMigrations(): array
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
        } catch (Throwable) {
            return ['(no migrations table)'];
        }

        $files = array_map(fn (string $path) => basename($path, '.php'), glob(database_path('migrations/*.php')) ?: []);

        return array_values(array_diff($files, $ran));
    }

    /**
     * @return array{waiting: int, failed: int}
     */
    private function queueCounts(): array
    {
        try {
            return ['waiting' => DB::table('jobs')->count(), 'failed' => DB::table('failed_jobs')->count()];
        } catch (Throwable) {
            return ['waiting' => 0, 'failed' => 0];
        }
    }
}
