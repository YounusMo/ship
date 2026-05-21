<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tracking\Services\Stickers\StickerPdfRenderer;
use App\Modules\Tracking\Services\Stickers\StickerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 *   php artisan stickers:generate {quantity} --user=42
 *   php artisan stickers:generate 500 --user=1 --notes="Q2 print run"
 *
 * Creates a sticker_batches row + N sticker ULIDs, renders the PDF, saves
 * it to the configured disk, and prints the saved path.
 */
class GenerateStickerBatchCommand extends Command
{
    protected $signature = 'stickers:generate
                            {quantity : Number of stickers to create (1..10000)}
                            {--user= : User ID who is generating this batch (required)}
                            {--notes= : Free-text notes attached to the batch}';

    protected $description = 'Create a batch of pre-printable QR stickers and render the PDF.';

    public function handle(StickerService $service, StickerPdfRenderer $renderer): int
    {
        $qty  = (int) $this->argument('quantity');
        $user = $this->option('user');

        if ($user === null || $user === '') {
            $this->error('--user=<id> is required so we can record who generated this batch.');
            return self::FAILURE;
        }
        $userId = (int) $user;
        if (! DB::table('users')->where('id', $userId)->exists()) {
            $this->error("User #{$userId} not found.");
            return self::FAILURE;
        }

        $this->info("Issuing {$qty} stickers (user #{$userId})...");
        $batch = $service->issueBatch($qty, $userId, $this->option('notes'));
        $this->line("  batch_code = <fg=green>{$batch->batch_code}</> (id {$batch->id})");

        $this->info('Rendering PDF...');
        $pdf = $renderer->render($batch);

        $disk = (string) config('tracking.stickers.storage_disk', 'local');
        $path = "stickers/{$batch->batch_code}.pdf";
        Storage::disk($disk)->put($path, $pdf);

        $service->markBatchPrinted($batch->id, $path);

        $this->line("  PDF saved to disk <fg=green>{$disk}</> at <fg=green>{$path}</>");
        $this->line('  Stickers marked printed_at = now() for the whole batch.');
        return self::SUCCESS;
    }
}
