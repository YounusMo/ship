<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Models\User;
use App\Modules\Tracking\Notifications\StuckShipmentReminderNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StuckShipmentReminderTest extends TestCase
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

    public function test_notify_flag_sends_reminder_to_admin_users_when_stuck_rows_exist(): void
    {
        $admin = User::query()->create([
            'code'         => 'TEST-A-' . uniqid(),
            'name'         => 'Test Admin',
            'type'         => 'admin',
            'branch'       => 1,
            'email'        => 'admin' . uniqid() . '@test.local',
            'password'     => bcrypt('test'),
            'lang'         => 'en',
            'created_date' => now()->toDateString(),
            'created_time' => now()->toTimeString(),
        ]);

        // Seed a stuck custody event 30 days back. recorded_by_user_id has
        // a FK to users — reuse the admin we just inserted.
        $custodyId = DB::table('custody_events')->insertGetId([
            'shipment_source_table' => 'store_sea',
            'shipment_source_id'    => 999_001,
            'shipment_piece_id'     => null,
            'event_type'            => 'RECEIVED_AT_HUB',
            'to_branch_id'          => null,
            'recorded_by_user_id'   => $admin->id,
            'occurred_at'           => now()->subDays(30),
            'created_at'            => now()->subDays(30),
            'updated_at'            => now()->subDays(30),
        ]);

        Notification::fake();
        Artisan::call('tracking:reconcile-stuck', ['--days' => 7, '--notify' => true]);

        Notification::assertSentTo($admin, StuckShipmentReminderNotification::class);

        // Cleanup — DatabaseTransactions does not cover custody_events rows
        // inserted via DB::table on the default connection, so clean them
        // explicitly to avoid leaking across runs.
        DB::table('custody_events')->where('id', $custodyId)->delete();
        $admin->delete();
    }

    public function test_default_run_without_notify_does_not_send(): void
    {
        $user = User::query()->create([
            'code'         => 'TEST-U-' . uniqid(),
            'name'         => 'Test User',
            'type'         => 'admin',
            'branch'       => 1,
            'email'        => 'user' . uniqid() . '@test.local',
            'password'     => bcrypt('test'),
            'lang'         => 'en',
            'created_date' => now()->toDateString(),
            'created_time' => now()->toTimeString(),
        ]);

        $custodyId = DB::table('custody_events')->insertGetId([
            'shipment_source_table' => 'store_sea',
            'shipment_source_id'    => 999_002,
            'shipment_piece_id'     => null,
            'event_type'            => 'RECEIVED_AT_HUB',
            'recorded_by_user_id'   => $user->id,
            'occurred_at'           => now()->subDays(30),
            'created_at'            => now()->subDays(30),
            'updated_at'            => now()->subDays(30),
        ]);

        Notification::fake();
        Artisan::call('tracking:reconcile-stuck', ['--days' => 7]);

        Notification::assertNothingSent();

        DB::table('custody_events')->where('id', $custodyId)->delete();
        $user->delete();
    }
}
