<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\SmsCallbackController;
use App\Models\AiCall;
use App\Models\AuditLog;
use App\Models\DataRequest;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Backup;
use App\Services\DemoData;
use App\Services\Erasure;
use App\Services\PollingUnitImporter;
use App\Services\PushNotifier;
use App\Services\RegisterStatus;
use App\Services\WardMap;
use App\Support\Audit;
use App\Support\BackgroundRunner;
use App\Support\Secrets;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Operator tasks that would otherwise need a terminal: updating the
 * database, the register, and the background runner.
 */
class SystemController extends Controller
{
    public function show(RegisterStatus $register, PushNotifier $notifier, WardMap $wardMap): View
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
            'pushConfigured' => $notifier->configured(),
            'pushDevices' => PushSubscription::query()->count(),
            'wardMap' => $wardMap->load(),
            'secrets' => collect(Secrets::KEYS)->map(fn ($key, $name) => ['label' => $key[0], 'hint' => Secrets::hint($name), 'env' => Secrets::fromEnv($name)]),
            'ai' => [
                'month' => AiCall::monthSpend(),
                'budget' => Settings::float('ai.monthly_budget'),
                'calls' => AiCall::query()->where('created_at', '>=', now()->startOfMonth())->count(),
                'failed' => AiCall::query()->where('created_at', '>=', now()->startOfMonth())->where('status', '!=', 'ok')->count(),
                'cacheRead' => (int) AiCall::query()->where('created_at', '>=', now()->startOfMonth())->sum('cache_read_tokens'),
                'input' => (int) AiCall::query()->where('created_at', '>=', now()->startOfMonth())->sum('input_tokens'),
                'byPurpose' => AiCall::query()->where('created_at', '>=', now()->startOfMonth())->selectRaw('purpose, count(*) as n, sum(cost_usd) as cost')->groupBy('purpose')->get(),
                'recent' => AiCall::query()->with('user')->latest('created_at')->limit(8)->get(),
                'model' => config('messaging.ai.model'),
            ],
            'retention' => Erasure::retentionDate(),
            'retentionDone' => Settings::get('privacy.retention_done_at'),
            'openRequests' => DataRequest::query()->where('status', 'pending')->count(),
            'demo' => DemoData::logins(),
            'demoLoaded' => DemoData::loaded(),
            'volunteerUrl' => route('volunteers.api', JoinController::token()),
            'smsUrls' => [
                'Delivery reports' => route('sms.delivery', SmsCallbackController::token()),
                'Bulk SMS opt-out' => route('sms.opt-out', SmsCallbackController::token()),
                'Incoming messages (STOP)' => route('sms.inbox', SmsCallbackController::token()),
            ],
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
     * Create the Web Push (VAPID) keys, once.
     */
    public function pushKeys(PushNotifier $notifier): RedirectResponse
    {
        if (! $notifier->generateKeys()) {
            return back()->with('error', 'Notification keys already exist. Changing them would stop every device’s notifications.');
        }

        Audit::record('system.push_keys', 'Set up notification (VAPID) keys');

        return back()->with('status', 'Notifications are set up. Each person turns them on under Notifications.');
    }

    /**
     * Load fictional demo data for presentations (admin only, audited).
     */
    public function loadDemo(Request $request, DemoData $demo): RedirectResponse
    {
        try {
            $result = $demo->load($request->user());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Demo data couldn’t be loaded: '.$e->getMessage());
        }

        return redirect(route('system').'#demo')->with('status', 'Demo data loaded: '.number_format(array_sum($result['counts'])).' records. The demo sign-ins are on this page.');
    }

    /**
     * Remove exactly the rows the demo created.
     */
    public function removeDemo(DemoData $demo): RedirectResponse
    {
        $removed = $demo->remove();

        return redirect(route('system').'#demo')->with('status', 'Demo data removed: '.number_format(array_sum($removed)).' records.');
    }

    /**
     * Download a full backup as an AES-256 encrypted zip, with a password
     * the admin chooses (it isn't stored anywhere).
     */
    public function backup(Request $request, Backup $backup): BinaryFileResponse|RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:200', 'confirmed']], [
            'password.min' => 'Use at least 12 characters: the backup holds everyone’s records.',
        ]);

        @set_time_limit(600);

        try {
            ['path' => $path, 'rows' => $rows] = $backup->create($data['password']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'The backup couldn’t be made: '.$e->getMessage());
        }

        Audit::record('system.backup', 'Downloaded a full encrypted backup', rows: $rows);

        return response()->download($path, 'command-center-backup-'.Time::now()->format('Y-m-d-Hi').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /**
     * API keys for Claude and Africa's Talking, stored encrypted. A blank
     * field keeps the current value; "remove" clears it.
     */
    public function secrets(Request $request): RedirectResponse
    {
        $rules = collect(Secrets::KEYS)->mapWithKeys(fn ($key, $name) => [$name => ['nullable', 'string', 'max:300']])->all();
        $data = $request->validate($rules + ['remove' => ['nullable', 'string']]);

        if ($remove = $request->input('remove')) {
            abort_unless(array_key_exists($remove, Secrets::KEYS), 422);
            Secrets::set($remove, null);
            Audit::record('system.secret', 'Removed the '.Secrets::KEYS[$remove][0]);

            return back()->with('status', Secrets::KEYS[$remove][0].' removed.');
        }

        $changed = [];
        foreach (Secrets::KEYS as $name => [$label]) {
            if (filled($data[$name] ?? null)) {
                Secrets::set($name, $data[$name]);
                $changed[] = $label;
            }
        }

        if ($changed) {
            Audit::record('system.secret', 'Changed: '.implode(', ', $changed));
        }

        return back()->with('status', $changed ? 'Saved: '.implode(', ', $changed).'.' : 'Nothing changed.');
    }

    /**
     * Upload (or remove) the ward-boundary GeoJSON for the ward map.
     */
    public function wardMap(Request $request, WardMap $wardMap): RedirectResponse
    {
        if ($request->boolean('remove')) {
            $wardMap->forget();
            Audit::record('system.ward_map', 'Removed the ward map');

            return back()->with('status', 'Ward map removed. Maps show LGA tiles again.');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'attribution' => ['required', 'string', 'max:160'],
        ]);
        @set_time_limit(180);

        try {
            $result = $wardMap->import($request->file('file')->getRealPath(), $data['attribution']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        Audit::record('system.ward_map', "Loaded ward boundaries: {$result['matched']} wards matched", rows: $result['matched']);

        return back()->with($result['missing'] ? 'error' : 'status', "{$result['matched']} wards matched.".($result['missing'] ? " {$result['missing']} register wards have no shape".($result['unmatched'] ? '; unmatched in the file: '.implode('; ', array_slice($result['unmatched'], 0, 8)) : '').'.' : ''));
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
