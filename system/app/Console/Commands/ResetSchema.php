<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot rebuild of the database from the production-shaped SQL dump.
 *
 *   php artisan schema:reset
 *
 * Loads analysis/setup_clean_db_v2.sql, runs every statement against the
 * currently-connected DB, then truncates the `migrations` table so a
 * follow-up `php artisan migrate` runs all migrations fresh.
 *
 * This exists because plain `mysql -u ... < dump.sql` failed silently for
 * the developer once the default Laravel migrations had created a partial
 * conflicting schema. Doing it through Laravel avoids the mysql CLI entirely.
 */
class ResetSchema extends Command
{
    protected $signature = 'schema:reset
        {--file= : Path to the SQL file (defaults to analysis/setup_clean_db_v2.sql)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Drop and recreate the full custom schema from analysis/setup_clean_db_v2.sql';

    public function handle(): int
    {
        $file = $this->option('file')
            ?: realpath(base_path('../analysis/setup_clean_db_v2.sql'));

        if (!$file || !is_readable($file)) {
            $this->error("Cannot read SQL file: " . ($file ?: 'analysis/setup_clean_db_v2.sql'));
            return self::FAILURE;
        }

        $db = config('database.connections.' . config('database.default') . '.database');
        $this->warn("This will DROP every table currently in database `{$db}` and rebuild from:");
        $this->line("  $file");

        if (!$this->option('force') && !$this->confirm('Continue?', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            $this->error('Failed to read SQL file.');
            return self::FAILURE;
        }

        // Strip phpMyAdmin metadata that PDO can't / won't parse cleanly.
        $sql = preg_replace('/^--.*$/m', '', $sql);                 // comments
        $sql = preg_replace('@/\*!.*?\*/;?@s', '', $sql);           // version hints
        $sql = preg_replace('/^(SET|START|COMMIT|ROLLBACK)\b[^;]*;/mi', '', $sql);

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn ($s) => $s !== ''
        );

        $this->info('Running ' . count($statements) . ' statements…');

        $bar = $this->output->createProgressBar(count($statements));
        $bar->start();

        $failed = [];
        foreach ($statements as $stmt) {
            try {
                DB::unprepared($stmt . ';');
            } catch (\Throwable $e) {
                $failed[] = [
                    'stmt' => substr($stmt, 0, 120),
                    'err'  => $e->getMessage(),
                ];
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        if ($failed) {
            $this->error(count($failed) . ' statement(s) failed:');
            foreach (array_slice($failed, 0, 10) as $f) {
                $this->line('  - ' . $f['stmt']);
                $this->line('    ' . $f['err']);
            }
            return self::FAILURE;
        }

        // Migrations table now exists (re-created by the dump). Reset it and
        // pre-mark the default Laravel migrations as applied so `migrate`
        // won't try to re-create the tables the dump already populated.
        DB::table('migrations')->truncate();
        DB::table('migrations')->insert([
            ['migration' => '0001_01_01_000000_create_users_table',                  'batch' => 1],
            ['migration' => '0001_01_01_000001_create_cache_table',                  'batch' => 1],
            ['migration' => '0001_01_01_000002_create_jobs_table',                   'batch' => 1],
            ['migration' => '2025_07_24_083659_create_personal_access_tokens_table', 'batch' => 1],
        ]);

        $this->info('Schema rebuilt cleanly. Migrations table reset and default migrations marked.');
        $this->info('Next: run `php artisan migrate` to apply the 4 hardening migrations.');
        return self::SUCCESS;
    }
}
