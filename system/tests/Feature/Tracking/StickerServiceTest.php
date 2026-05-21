<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Tracking\Exceptions\StickerException;
use App\Modules\Tracking\Models\Sticker;
use App\Modules\Tracking\Models\StickerBatch;
use App\Modules\Tracking\Services\Stickers\StickerPdfRenderer;
use App\Modules\Tracking\Services\Stickers\StickerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StickerServiceTest extends TestCase
{
    use DatabaseTransactions;

    private StickerService $service;
    private int $userId;
    private int $pieceId;
    private int $secondPieceId;

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

        $this->service = app(StickerService::class);

        $this->userId = (int) DB::table('users')->insertGetId([
            'name'       => 'Sticker Test User',
            'email'      => 'sticker-test+' . uniqid() . '@example.com',
            'password'   => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Two synthetic pieces — the stickers FK to shipment_pieces but
        // these tests don't need a real shipment behind them.
        $this->pieceId = (int) DB::table('shipment_pieces')->insertGetId([
            'tracking_code' => 'TST' . uniqid(),
            'source_table'  => 'store_out_sea',
            'source_id'     => 999_999_001,
            'client_id'     => 1,
            'piece_index'   => 1,
            'piece_total'   => 1,
            'status'        => 'active',
            'created_by'    => $this->userId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $this->secondPieceId = (int) DB::table('shipment_pieces')->insertGetId([
            'tracking_code' => 'TST' . uniqid(),
            'source_table'  => 'store_out_sea',
            'source_id'     => 999_999_002,
            'client_id'     => 1,
            'piece_index'   => 1,
            'piece_total'   => 1,
            'status'        => 'active',
            'created_by'    => $this->userId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_issue_batch_creates_n_stickers(): void
    {
        $batch = $this->service->issueBatch(50, $this->userId, 'Q2 print run');

        $this->assertInstanceOf(StickerBatch::class, $batch);
        $this->assertEquals(50, $batch->quantity);
        $this->assertStringStartsWith('SB-', $batch->batch_code);

        $count = Sticker::query()->where('batch_id', $batch->id)->count();
        $this->assertSame(50, $count);

        // ULIDs are 26 chars
        $first = Sticker::query()->where('batch_id', $batch->id)->first();
        $this->assertSame(26, strlen($first->id));
        $this->assertEquals("shipflow://qr/{$first->id}", $first->qrPayload());
    }

    public function test_quantity_must_be_in_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->issueBatch(0, $this->userId);
    }

    public function test_assign_to_piece_happy_path_and_idempotent(): void
    {
        $batch = $this->service->issueBatch(2, $this->userId);
        $sticker = Sticker::query()->where('batch_id', $batch->id)->first();

        $assigned = $this->service->assignToPiece($sticker->id, $this->pieceId);
        $this->assertEquals($this->pieceId, $assigned->shipment_piece_id);
        $this->assertNotNull($assigned->assigned_at);

        // Re-assign to same piece — idempotent.
        $again = $this->service->assignToPiece($sticker->id, $this->pieceId);
        $this->assertEquals($this->pieceId, $again->shipment_piece_id);
    }

    public function test_assign_to_different_piece_throws(): void
    {
        $batch = $this->service->issueBatch(1, $this->userId);
        $sticker = Sticker::query()->where('batch_id', $batch->id)->first();
        $this->service->assignToPiece($sticker->id, $this->pieceId);

        $this->expectException(StickerException::class);
        $this->expectExceptionMessageMatches('/already assigned/i');
        $this->service->assignToPiece($sticker->id, $this->secondPieceId);
    }

    public function test_assign_revoked_throws(): void
    {
        $batch = $this->service->issueBatch(1, $this->userId);
        $sticker = Sticker::query()->where('batch_id', $batch->id)->first();
        $this->service->revoke($sticker->id, 'misprint');

        $this->expectException(StickerException::class);
        $this->expectExceptionMessageMatches('/revoked/i');
        $this->service->assignToPiece($sticker->id, $this->pieceId);
    }

    public function test_double_revoke_throws(): void
    {
        $batch = $this->service->issueBatch(1, $this->userId);
        $sticker = Sticker::query()->where('batch_id', $batch->id)->first();
        $this->service->revoke($sticker->id, 'damaged');

        $this->expectException(StickerException::class);
        $this->expectExceptionMessageMatches('/already revoked/i');
        $this->service->revoke($sticker->id, 'again');
    }

    public function test_pdf_render_produces_valid_pdf_bytes(): void
    {
        $batch = $this->service->issueBatch(8, $this->userId, 'pdf test');
        $renderer = app(StickerPdfRenderer::class);

        $bytes = $renderer->render($batch);

        $this->assertNotEmpty($bytes);
        $this->assertSame('%PDF', substr($bytes, 0, 4), 'Output should be a real PDF');
        $this->assertGreaterThan(2000, strlen($bytes), 'A PDF with 8 QR codes should be at least a couple KB');
    }

    public function test_mark_batch_printed_stamps_pdf_path_and_stickers(): void
    {
        $batch = $this->service->issueBatch(3, $this->userId);
        $this->service->markBatchPrinted($batch->id, 'stickers/test.pdf');

        $batch->refresh();
        $this->assertEquals('stickers/test.pdf', $batch->pdf_path);

        $printedCount = Sticker::query()
            ->where('batch_id', $batch->id)
            ->whereNotNull('printed_at')
            ->count();
        $this->assertSame(3, $printedCount);
    }
}
