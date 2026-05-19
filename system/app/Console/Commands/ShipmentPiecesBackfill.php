<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\shipmentStickersController;

/**
 * Walk store_sea + store_sky and create any missing shipment_pieces rows
 * for batches that were received before per-piece tracking was wired in.
 *
 * Idempotent — already-pieced rows are skipped via ensurePieces().
 */
class ShipmentPiecesBackfill extends Command
{
    protected $signature   = 'shipments:pieces-backfill {--dry-run : Print plan, do not insert}';
    protected $description = 'Backfill shipment_pieces from existing store_sea + store_sky rows.';

    public function handle(): int
    {
        $dry      = (bool) $this->option('dry-run');
        $stickers = new shipmentStickersController();
        $stats    = ['scanned' => 0, 'pieced' => 0, 'pieces_created' => 0, 'skipped_zero' => 0];

        foreach (['store_sea', 'store_sky'] as $table) {
            $this->info("-- $table");

            // Stream in 500-row chunks: a production-sized store_sea can be
            // tens of thousands of rows. Wrap the OR in a closure so future
            // chained where()s don't collapse operator precedence.
            DB::table($table)
                ->where(function ($q) {
                    $q->whereNull('canceled')->orWhere('canceled', '!=', 'true');
                })
                ->select('id', 'client_id', 'number')
                ->orderBy('id')
                ->lazyById(500)
                ->each(function ($r) use ($table, $dry, $stickers, &$stats) {
                    $stats['scanned']++;
                    $count = max(0, (int) ($r->number ?? 0));
                    if ($count < 1) {
                        $stats['skipped_zero']++;
                        return;
                    }

                    $existing = DB::table('shipment_pieces')
                        ->where('source_table', $table)
                        ->where('source_id', $r->id)
                        ->count();

                    if ($existing >= $count) return;

                    if ($dry) {
                        $missing = $count - $existing;
                        $this->line("  + $table#{$r->id}: would create $missing piece(s)");
                        $stats['pieced']++;
                        $stats['pieces_created'] += $missing;
                        return;
                    }

                    $clientId = (int) ($r->client_id ?? 0) ?: null;
                    try {
                        $stickers->ensurePieces($table, (int) $r->id, $count, $clientId);
                    } catch (\Throwable $e) {
                        // One bad row shouldn't abort the whole backfill.
                        $this->error("  FAILED $table#{$r->id}: {$e->getMessage()}");
                        return;
                    }
                    $after = DB::table('shipment_pieces')
                        ->where('source_table', $table)
                        ->where('source_id', $r->id)
                        ->count();

                    $stats['pieced']++;
                    $stats['pieces_created'] += max(0, $after - $existing);
                });
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. Scanned %d rows · pieced %d · created %d pieces · skipped %d (number<1).',
            $stats['scanned'], $stats['pieced'], $stats['pieces_created'], $stats['skipped_zero']
        ));
        if ($dry) $this->warn('Dry run — nothing was written.');
        return 0;
    }
}
