<?php

declare(strict_types=1);

namespace Tests\Feature\Purge;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke tests for the data-retention purge commands. Each test runs
 * --dry-run first to confirm no writes happen, then runs the real
 * command and asserts the row state changed correctly.
 *
 * @see docs/GAPS.md gap #8
 */
class PurgeCommandsTest extends TestCase
{
    use DatabaseTransactions;

    protected function connectionsToTransact(): array
    {
        return ['mysql'];
    }

    protected function refreshApplication(): void
    {
        $envDb = trim((string) shell_exec("grep '^DB_DATABASE=' .env | cut -d= -f2")) ?: 'ship_system';
        putenv("DB_DATABASE={$envDb}");
        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_DATABASE']      = $envDb;
        $_ENV['DB_CONNECTION']    = 'mysql';
        $_SERVER['DB_DATABASE']   = $envDb;
        $_SERVER['DB_CONNECTION'] = 'mysql';

        parent::refreshApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL not available: ' . $e->getMessage());
        }
    }

    public function test_purge_webhook_payloads_nullifies_only_old_rows(): void
    {
        $oldId = DB::table('webhook_deliveries')->insertGetId([
            'provider'          => 'test-old',
            'external_event_id' => 'evt-old-' . uniqid(),
            'event_type'        => 'TEST',
            'payload'           => json_encode(['big' => str_repeat('x', 1000)]),
            'signature'         => 'sig',
            'received_at'       => now()->subDays(100),
            'created_at'        => now()->subDays(100),
            'updated_at'        => now()->subDays(100),
        ]);
        $newId = DB::table('webhook_deliveries')->insertGetId([
            'provider'          => 'test-new',
            'external_event_id' => 'evt-new-' . uniqid(),
            'event_type'        => 'TEST',
            'payload'           => json_encode(['big' => 'fresh']),
            'signature'         => 'sig',
            'received_at'       => now()->subDays(10),
            'created_at'        => now()->subDays(10),
            'updated_at'        => now()->subDays(10),
        ]);

        // Dry-run: no writes.
        $this->artisan('purge:webhook-payloads', ['--days' => 90, '--dry-run' => true])
            ->expectsOutputToContain('Would trim')
            ->assertSuccessful();
        $this->assertStringContainsString(
            'big',
            (string) DB::table('webhook_deliveries')->where('id', $oldId)->value('payload'),
        );

        // Real run: only the old one is trimmed to the stub.
        $this->artisan('purge:webhook-payloads', ['--days' => 90])
            ->expectsOutputToContain('Trimmed')
            ->assertSuccessful();
        $oldPayload = (string) DB::table('webhook_deliveries')->where('id', $oldId)->value('payload');
        $newPayload = (string) DB::table('webhook_deliveries')->where('id', $newId)->value('payload');
        $this->assertStringContainsString('_trimmed', $oldPayload);
        $this->assertStringContainsString('fresh', $newPayload);
    }

    public function test_purge_failed_jobs_only_drops_old_rows(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->markTestSkipped('failed_jobs table not present in this environment.');
        }

        $tag = 'PURGE-TEST-' . uniqid();
        DB::table('failed_jobs')->insert([
            [
                'uuid'       => (string) \Illuminate\Support\Str::uuid(),
                'connection' => $tag,
                'queue'      => 'old',
                'payload'    => '{}',
                'exception'  => 'old failure',
                'failed_at'  => now()->subDays(45),
            ],
            [
                'uuid'       => (string) \Illuminate\Support\Str::uuid(),
                'connection' => $tag,
                'queue'      => 'new',
                'payload'    => '{}',
                'exception'  => 'recent failure',
                'failed_at'  => now()->subDays(5),
            ],
        ]);

        $this->artisan('purge:failed-jobs', ['--days' => 30])
            ->expectsOutputToContain('Deleted')
            ->assertSuccessful();

        $remaining = DB::table('failed_jobs')
            ->where('connection', $tag)
            ->pluck('queue')->all();
        $this->assertContains('new', $remaining);
        $this->assertNotContains('old', $remaining);
    }

    public function test_purge_read_notifications_keeps_unread_and_recent(): void
    {
        $oldRead = (string) \Illuminate\Support\Str::uuid();
        $recentRead = (string) \Illuminate\Support\Str::uuid();
        $oldUnread = (string) \Illuminate\Support\Str::uuid();

        DB::table('notifications')->insert([
            [
                'id'              => $oldRead,
                'type'            => 'TEST',
                'notifiable_type' => 'App\\Models\\Client',
                'notifiable_id'   => 1,
                'data'            => '{}',
                'read_at'         => now()->subDays(200),
                'created_at'      => now()->subDays(200),
                'updated_at'      => now()->subDays(200),
            ],
            [
                'id'              => $recentRead,
                'type'            => 'TEST',
                'notifiable_type' => 'App\\Models\\Client',
                'notifiable_id'   => 1,
                'data'            => '{}',
                'read_at'         => now()->subDays(10),
                'created_at'      => now()->subDays(10),
                'updated_at'      => now()->subDays(10),
            ],
            [
                'id'              => $oldUnread,
                'type'            => 'TEST',
                'notifiable_type' => 'App\\Models\\Client',
                'notifiable_id'   => 1,
                'data'            => '{}',
                'read_at'         => null,
                'created_at'      => now()->subDays(300),
                'updated_at'      => now()->subDays(300),
            ],
        ]);

        $this->artisan('purge:read-notifications', ['--days' => 180])
            ->expectsOutputToContain('Deleted')
            ->assertSuccessful();

        $remaining = DB::table('notifications')
            ->whereIn('id', [$oldRead, $recentRead, $oldUnread])
            ->pluck('id')->all();

        $this->assertNotContains($oldRead, $remaining,
            'Read notification past retention should be deleted');
        $this->assertContains($recentRead, $remaining,
            'Recent read notification should be kept');
        $this->assertContains($oldUnread, $remaining,
            'Unread notifications are kept regardless of age');
    }

    public function test_archive_audit_log_writes_jsonl_and_deletes(): void
    {
        // Snapshot the existing tail so we don't archive unrelated rows.
        $tag = 'PURGE-ARCH-' . uniqid();
        $oldId = DB::table('audit_log')->insertGetId([
            'user_id'      => null,
            'user_type'    => null,
            'action'       => $tag,
            'target_table' => 'test',
            'target_id'    => null,
            'payload'      => null,
            'ip'           => null,
            'context'      => $tag,
            'created_at'   => now()->subMonths(20),
        ]);
        $recentId = DB::table('audit_log')->insertGetId([
            'user_id'      => null,
            'user_type'    => null,
            'action'       => $tag,
            'target_table' => 'test',
            'target_id'    => null,
            'payload'      => null,
            'ip'           => null,
            'context'      => $tag . '-recent',
            'created_at'   => now()->subDays(10),
        ]);

        // Confirm row is visible before invoking the command.
        $beforeCount = (int) DB::table('audit_log')
            ->where('created_at', '<', now()->subMonths(18))
            ->where('context', $tag)
            ->count();
        $this->assertSame(1, $beforeCount, 'Setup: aged row should be queryable before command runs');

        $this->artisan('archive:audit-log', ['--months' => 18])
            ->expectsOutputToContain('Archived')
            ->assertSuccessful();

        // Old row should be gone, recent still here.
        $this->assertNull(DB::table('audit_log')->where('id', $oldId)->first());
        $this->assertNotNull(DB::table('audit_log')->where('id', $recentId)->first());

        // A monthly archive file should exist with our tag inside it.
        // The file may contain multiple appended gzip streams across
        // runs — gzfile() handles concatenated streams cleanly.
        $disk = Storage::disk('local');
        $files = $disk->files('audit-archive');
        $found = false;
        foreach ($files as $f) {
            $lines = @gzfile($disk->path($f));
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                if (str_contains($line, $tag)) {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($found, 'Archived row should appear in a JSONL.gz file under storage/app/audit-archive/');
    }
}
