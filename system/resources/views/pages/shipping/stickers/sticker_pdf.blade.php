@php
    /**
     * 100x150mm thermal shipping label, one sticker per page.
     * Strict black & white.
     *
     * Laid out as stacked divs (not a single table) because mPDF refuses
     * to break a tall table row across pages and inflates row heights —
     * stacked blocks fit predictably on one page.
     */
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    * { font-family: dejavusans, sans-serif; box-sizing: border-box; }
    body { margin: 0; color: #000; font-size: 9pt; }

    .sticker {
        width: 92mm;
        border: 1.2pt solid #000;
        page-break-inside: avoid;
    }
    .sticker.brk { page-break-after: always; }

    /* === Header band === */
    .hdr {
        background: #000;
        color: #fff;
        padding: 1.8mm 2.5mm;
    }
    .hdr table { width: 100%; }
    .hdr td { vertical-align: middle; color: #fff; padding: 0; }
    .mode {
        border: 0.8pt solid #fff;
        padding: 0.5mm 2mm;
        font-size: 9pt;
        font-weight: 800;
        letter-spacing: 2pt;
        white-space: nowrap;
    }
    .biz {
        font-size: 11pt;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1pt;
        text-align: center;
    }
    .logo {
        display: inline-block;
        width: 8mm; height: 8mm;
        border: 0.8pt solid #fff;
        color: #fff;
        text-align: center;
        font-size: 13pt;
        font-weight: 800;
        line-height: 8mm;
    }

    /* === SHIP FROM / RECIPIENT === */
    .addr { border-top: 0.8pt solid #000; }
    .addr table { width: 100%; border-collapse: collapse; }
    .addr td { padding: 2mm 3mm; vertical-align: top; }
    .addr td.l { border-right: 0.8pt solid #000; width: 45%; }
    .lbl {
        font-size: 6pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.2pt;
    }
    .val { font-size: 10pt; font-weight: 600; margin-top: 1mm; }
    .client-code {
        font-size: 22pt;
        font-weight: 900;
        line-height: 1;
        margin-top: 1mm;
        letter-spacing: 1pt;
    }

    /* === QR === */
    .qr {
        text-align: center;
        padding: 1.5mm 0 1mm;
        border-top: 0.8pt solid #000;
    }
    .qr img { width: 50mm; height: 50mm; }

    /* === Piece band === */
    .piece {
        background: #000;
        color: #fff;
        text-align: center;
        font-size: 10pt;
        font-weight: 800;
        letter-spacing: 2.5pt;
        padding: 1.2mm 0;
        text-transform: uppercase;
    }

    /* === Barcode === */
    .bc { text-align: center; padding: 1.5mm 0 0.5mm; }
    .bc img { width: 78mm; height: 11mm; }

    /* === Tracking text === */
    .code {
        text-align: center;
        font-family: dejavusansmono, monospace;
        font-weight: 700;
        font-size: 11pt;
        letter-spacing: 1.8pt;
        padding-bottom: 1.2mm;
    }

    /* === Footer === */
    .ft {
        text-align: center;
        font-size: 6.5pt;
        letter-spacing: 0.5pt;
        border-top: 0.6pt solid #000;
        padding: 1mm 0;
    }
</style>
</head>
<body>
@php
    $flat = [];
    foreach ($batches as $b) {
        foreach ($b['stickers'] as $st) $flat[] = ['st' => $st, 'b' => $b];
    }
@endphp
@foreach ($flat as $entry)
    @php
        $s      = $entry['st'];
        $p      = $s['piece'];
        $source = $entry['b']['source'];
        $client = $entry['b']['client'];
        $mode   = $entry['b']['mode'];
    @endphp
    <div class="sticker {{ $loop->last ? '' : 'brk' }}">

        <div class="hdr">
            <table>
                <tr>
                    <td style="width: 17mm; text-align: left;">
                        <span class="mode">{{ $mode === 'air' ? $lang->write('AIR') : $lang->write('SEA') }}</span>
                    </td>
                    <td><div class="biz">{{ $settings['company_name'] ?? '' }}</div></td>
                    <td style="width: 12mm; text-align: right;">
                        <span class="logo">{{ \App\Http\Controllers\settingsController::brandInitial($settings) }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="addr">
            <table>
                <tr>
                    <td class="l">
                        <div class="lbl">{{ $lang->write('Ship from') }}</div>
                        <div class="val">{{ ucfirst($source->ship_from ?? '—') }}</div>
                    </td>
                    <td>
                        <div class="lbl">{{ $lang->write('Recipient') }}</div>
                        <div class="client-code">{{ $client->code ?? '—' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="qr"><img src="{{ $s['qr_data_uri'] }}" alt="QR" style="width: 56mm; height: 56mm;" width="56mm" height="56mm"></div>

        <div class="piece">{{ $lang->write('Piece') }} {{ $p->piece_index }} {{ $lang->write('of') }} {{ $p->piece_total }}</div>

        <div class="bc"><img src="{{ $s['barcode_data_uri'] }}" alt="Barcode" style="width: 78mm; height: 11mm;" width="78mm" height="11mm"></div>
        <div class="code">{{ $s['pretty_code'] }}</div>

        <div class="ft">
            @if (!empty($source->created_date))
                {{ strtoupper($lang->write('Received')) }} {{ $source->created_date }}
            @endif
        </div>

    </div>
@endforeach
</body>
</html>
