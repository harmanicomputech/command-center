<?php

namespace App\Services;

use App\Support\Time;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * A full backup as an AES-256 encrypted zip (adapted from Election
 * Shield's DataMaintenance::backup()). One CSV per table, written to temp
 * files so 500,000 voters don't fill memory. Never included: passwords,
 * remember and invite tokens, settings (they hold API keys), push
 * subscriptions, sessions and the queue. Voter phone numbers stay
 * encrypted with APP_KEY, so keep a copy of the key somewhere safe.
 */
class Backup
{
    /** Tables, with columns left out. */
    private const TABLES = [
        'lgas' => [], 'wards' => [], 'polling_units' => [],
        'users' => ['password', 'remember_token', 'invite_token_hash'],
        'voters' => [], 'influencers' => [], 'events' => [], 'event_user' => [],
        'tasks' => [], 'task_reports' => [], 'issues' => [], 'photos' => [], 'rewards' => [],
        'past_results' => [], 'segments' => [],
        'surveys' => [], 'survey_questions' => [], 'survey_responses' => ['phone_hash'],
        'policy_documents' => [], 'message_drafts' => [], 'ai_calls' => [],
        'broadcasts' => [], 'broadcast_messages' => [], 'sms_opt_outs' => [],
        'narratives' => [], 'narrative_reports' => [], 'news_feeds' => [], 'news_items' => [], 'page_posts' => [],
        'data_requests' => ['phone'], 'audit_logs' => [],
    ];

    /**
     * Write the encrypted backup to a temporary file and return its path
     * and the row count.
     *
     * @return array{path: string, rows: int}
     */
    public function create(string $password): array
    {
        if (! defined('ZipArchive::EM_AES_256')) {
            throw new RuntimeException('This server’s PHP can’t encrypt zip files (libzip without AES).');
        }

        $path = tempnam(sys_get_temp_dir(), 'cc-backup-');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup file.');
        }
        $zip->setPassword($password);

        $summary = [];
        $total = 0;
        $temps = [];

        foreach (self::TABLES as $table => $excluded) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_diff(Schema::getColumnListing($table), $excluded));
            $file = tempnam(sys_get_temp_dir(), 'cc-table-');
            $temps[] = $file;
            $csv = fopen($file, 'w');
            fputcsv($csv, $columns, escape: '\\');
            $rows = 0;

            $query = DB::table($table)->select($columns);
            $write = function ($row) use ($csv, $columns, &$rows) {
                fputcsv($csv, array_map(fn ($column) => $row->{$column}, $columns), escape: '\\');
                $rows++;
            };
            in_array('id', $columns, true)
                ? $query->orderBy('id')->lazyById(2000)->each($write)
                : $query->orderBy($columns[0])->lazy(2000)->each($write);
            fclose($csv);

            $zip->addFile($file, "{$table}.csv");
            $zip->setEncryptionName("{$table}.csv", ZipArchive::EM_AES_256);
            $summary[] = str_pad($table, 22).number_format($rows);
            $total += $rows;
        }

        $zip->addFromString('README.txt', implode("\n", [
            'Command Center: full backup',
            'Taken '.Time::now()->format('l j F Y, g:i A T').' from '.config('app.url'),
            '',
            'One CSV per table (UTF-8, first row = column names). Times are UTC.',
            'Voter and contact phone numbers are encrypted with the APP_KEY in .env:',
            'keep a copy of that key somewhere safe, apart from this file.',
            'Not included: passwords and tokens, settings (they hold API keys), push',
            'subscriptions, sessions, and the photo files (copy',
            'command-center/storage/app/private/photos with the File Manager).',
            '',
            'Rows per table:',
            ...$summary,
        ]));
        $zip->setEncryptionName('README.txt', ZipArchive::EM_AES_256);
        $zip->close();

        array_map('unlink', $temps);

        return ['path' => $path, 'rows' => $total];
    }
}
