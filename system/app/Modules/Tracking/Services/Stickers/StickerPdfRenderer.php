<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services\Stickers;

use App\Modules\Tracking\Models\Sticker;
use App\Modules\Tracking\Models\StickerBatch;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;

/**
 * Renders a printable PDF for an entire sticker batch. Layout is a fixed
 * grid (4 columns × 8 rows = 32 per A4 page) sized for standard 38×21mm
 * adhesive labels. Each cell holds the QR + the last 8 chars of the ULID
 * underneath for human readability.
 *
 * Returns raw PDF bytes — the caller decides where to store them
 * (typically via Storage::disk(config('tracking.stickers.storage_disk'))).
 */
class StickerPdfRenderer
{
    private const COLS = 4;
    private const ROWS = 8;

    public function render(StickerBatch $batch): string
    {
        $stickers = Sticker::query()
            ->where('batch_id', $batch->id)
            ->orderBy('id')
            ->get();

        $html = $this->buildHtml($batch, $stickers);

        $mpdf = new Mpdf([
            'format'        => 'A4',
            'margin_left'   => 8,
            'margin_right'  => 8,
            'margin_top'    => 8,
            'margin_bottom' => 8,
        ]);
        $mpdf->SetTitle('ShipFlow stickers — ' . $batch->batch_code);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * @param  Collection<int, Sticker>  $stickers
     */
    private function buildHtml(StickerBatch $batch, Collection $stickers): string
    {
        $perPage = self::COLS * self::ROWS;
        $pages = $stickers->chunk($perPage);

        $css = <<<'CSS'
<style>
    body { font-family: sans-serif; margin: 0; }
    h1   { font-size: 12px; margin: 0 0 6px 0; }
    .grid { width: 100%; border-collapse: collapse; }
    .grid td {
        width: 25%;
        height: 90px;
        border: 1px dashed #aaa;
        padding: 4px;
        vertical-align: middle;
        text-align: center;
    }
    .grid img { width: 65px; height: 65px; }
    .grid .code {
        font-family: monospace;
        font-size: 8px;
        color: #444;
        margin-top: 2px;
        letter-spacing: 0.5px;
    }
</style>
CSS;

        $body = '';
        foreach ($pages as $pageIdx => $pageStickers) {
            if ($pageIdx > 0) {
                $body .= '<pagebreak/>';
            }
            $body .= sprintf(
                '<h1>ShipFlow %s &nbsp; — &nbsp; page %d of %d &nbsp; — &nbsp; %d stickers</h1>',
                htmlspecialchars($batch->batch_code, ENT_QUOTES),
                $pageIdx + 1,
                $pages->count(),
                $pageStickers->count(),
            );
            $body .= '<table class="grid">';

            $cells = $pageStickers->values();
            for ($r = 0; $r < self::ROWS; $r++) {
                $body .= '<tr>';
                for ($c = 0; $c < self::COLS; $c++) {
                    $sticker = $cells->get($r * self::COLS + $c);
                    if ($sticker === null) {
                        $body .= '<td></td>';
                        continue;
                    }
                    $dataUri = $this->qrDataUri($sticker->qrPayload());
                    $short = strtoupper(substr($sticker->id, -8));
                    $body .= sprintf(
                        '<td><img src="%s"/><div class="code">%s</div></td>',
                        $dataUri,
                        $short,
                    );
                }
                $body .= '</tr>';
            }
            $body .= '</table>';
        }

        return "<!DOCTYPE html><html><head><meta charset=\"utf-8\">{$css}</head><body>{$body}</body></html>";
    }

    private function qrDataUri(string $payload): string
    {
        // endroid/qr-code v6 uses constructor args, not the v4 fluent
        // ->create()->writer()->... chain.
        $builder = new Builder(
            writer: new PngWriter(),
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 180,
            margin: 6,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $builder->build()->getDataUri();
    }
}
