<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services\Stickers;

use App\Modules\Tracking\Exceptions\StickerException;
use App\Modules\Tracking\Models\Sticker;
use App\Modules\Tracking\Models\StickerBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues bulk batches of opaque ULID-keyed stickers and assigns them to
 * shipment_pieces at scan time.
 *
 * The design intent is "dumb sticker, smart backend" — a sticker carries
 * only its ULID, never any data about the customer, shipment, or
 * destination. Routing is resolved at scan time. This lets us pre-print
 * sticker rolls in bulk without committing to any operational state.
 */
class StickerService
{
    /**
     * Create a batch and N stickers in one transaction. Each sticker
     * gets a fresh ULID generated up-front so the batch is immediately
     * printable (no race with assignToPiece() handing out duplicate ids).
     *
     * @return StickerBatch
     */
    public function issueBatch(int $quantity, int $generatedByUserId, ?string $notes = null): StickerBatch
    {
        if ($quantity <= 0 || $quantity > 10_000) {
            throw new \InvalidArgumentException(
                "Quantity must be 1..10000, got {$quantity}",
            );
        }

        return DB::transaction(function () use ($quantity, $generatedByUserId, $notes) {
            $batch = StickerBatch::create([
                'batch_code'           => $this->nextBatchCode(),
                'quantity'             => $quantity,
                'generated_by_user_id' => $generatedByUserId,
                'generated_at'         => now(),
                'notes'                => $notes,
            ]);

            $now = now();
            $rows = [];
            for ($i = 0; $i < $quantity; $i++) {
                $rows[] = [
                    'id'         => (string) Str::ulid(),
                    'batch_id'   => $batch->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            // chunked insert keeps the prepared-statement reasonable size.
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('stickers')->insert($chunk);
            }

            return $batch->fresh();
        });
    }

    /**
     * Assign a previously-issued sticker to a specific shipment_piece.
     * Idempotent on the same piece (no-op + return the sticker).
     */
    public function assignToPiece(string $stickerId, int $pieceId): Sticker
    {
        return DB::transaction(function () use ($stickerId, $pieceId) {
            $sticker = Sticker::query()->lockForUpdate()->find($stickerId);
            if ($sticker === null) {
                throw StickerException::notFound($stickerId);
            }
            if ($sticker->revoked_at !== null) {
                throw StickerException::revoked($stickerId);
            }
            if ($sticker->shipment_piece_id !== null && $sticker->shipment_piece_id !== $pieceId) {
                throw StickerException::alreadyAssigned($stickerId, (int) $sticker->shipment_piece_id);
            }
            if ($sticker->shipment_piece_id === $pieceId) {
                return $sticker; // idempotent.
            }

            $sticker->shipment_piece_id = $pieceId;
            $sticker->assigned_at = now();
            $sticker->save();

            return $sticker;
        });
    }

    public function revoke(string $stickerId, string $reason): Sticker
    {
        return DB::transaction(function () use ($stickerId, $reason) {
            $sticker = Sticker::query()->lockForUpdate()->find($stickerId);
            if ($sticker === null) {
                throw StickerException::notFound($stickerId);
            }
            if ($sticker->revoked_at !== null) {
                throw StickerException::alreadyRevoked($stickerId);
            }

            $sticker->revoked_at = now();
            $sticker->revoke_reason = $reason;
            $sticker->save();

            return $sticker;
        });
    }

    public function markBatchPrinted(int $batchId, string $pdfPath): void
    {
        DB::table('sticker_batches')->where('id', $batchId)->update([
            'pdf_path'   => $pdfPath,
            'updated_at' => now(),
        ]);
        // Per-sticker printed_at touch so reporting "how many stickers
        // printed this month" doesn't have to join through the batch.
        DB::table('stickers')
            ->where('batch_id', $batchId)
            ->whereNull('printed_at')
            ->update(['printed_at' => now(), 'updated_at' => now()]);
    }

    private function nextBatchCode(): string
    {
        $prefix = 'SB-' . date('Ymd');
        $today = DB::table('sticker_batches')
            ->where('batch_code', 'like', $prefix . '%')
            ->count();
        return sprintf('%s-%03d', $prefix, $today + 1);
    }
}
