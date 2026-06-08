@php
    $displayCcy = strtoupper($req->display_currency ?: $req->currency ?: 'usd');
    $rates = !empty($req->fx_rate_snapshot)
        ? (json_decode($req->fx_rate_snapshot, true) ?: [])
        : $data->currency_exchange_rates;

    $toDisplay = function ($amount, $ccy) use ($displayCcy, $rates) {
        $ccy = strtolower((string) $ccy);
        $disp = strtolower($displayCcy);
        if ($ccy === $disp) return (float) $amount;
        $usd = $ccy === 'usd' ? (float) $amount : ((float) ($rates[$ccy] ?? 0) > 0 ? (float) $amount / (float) $rates[$ccy] : 0);
        return $disp === 'usd' ? $usd : $usd * (float) ($rates[$disp] ?? 1);
    };

    $logoPath = \App\Http\Controllers\settingsController::brandLogoPath();

    // Status pill — small + neutral by default; only Approved gets a
    // colored accent. We don't want the whole top of the document to
    // be dominated by a status.
    $statusMap = [
        'quoted'    => ['label' => 'DRAFT',     'bg' => '#e5e7eb', 'fg' => '#374151'],
        'accepted'  => ['label' => 'APPROVED',  'bg' => '#065f46', 'fg' => '#ecfdf5'],
        'fulfilled' => ['label' => 'FULFILLED', 'bg' => '#1e3a8a', 'fg' => '#eff6ff'],
        'canceled'  => ['label' => 'CANCELED',  'bg' => '#991b1b', 'fg' => '#fef2f2'],
        'open'      => ['label' => 'OPEN',      'bg' => '#e5e7eb', 'fg' => '#374151'],
        'searching' => ['label' => 'SEARCHING', 'bg' => '#e5e7eb', 'fg' => '#374151'],
    ];
    $statusInfo = $statusMap[$req->status] ?? ['label' => strtoupper($req->status ?? ''), 'bg' => '#e5e7eb', 'fg' => '#374151'];

    $ink        = '#111827';   // primary text
    $muted      = '#6b7280';   // secondary text
    $rule       = '#d1d5db';   // visible but quiet rule
    $faint      = '#f3f4f6';   // subtle row tint / band
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma {{ $req->request_number }}</title>
    <style>
        /* 32px margins all round — tighter than typical "letter"
           but the whole point is to fit a normal proforma on ONE
           page. Increase if your stationery has a printed header. */
        @page { margin: 32px 36px 36px; }
        body {
            font-family: dejavusans, sans-serif;
            color: {{ $ink }};
            font-size: 9pt;
            line-height: 1.4;
            margin: 0;
        }

        /* ---------- Header (title left, logo right) ---------- */
        /* Uses real <table> markup (not display:table on divs) because
           mpdf renders display:table inconsistently — title and logo
           end up stacked instead of side-by-side. */
        .header { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .header .h-left  { vertical-align: middle; width: 65%; }
        .header .h-right { vertical-align: middle; width: 35%; text-align: right; }
        .doc-title {
            font-size: 16pt;
            font-weight: 700;
            letter-spacing: 3px;
            color: {{ $ink }};
            text-transform: uppercase;
        }
        /* Logo capped per operator request: max 100px. Both axes
           bounded so wide horizontal logos don't blow up vertically. */
        .logo-box img { max-height: 50px; max-width: 100px; }
        .logo-box .brand-text {
            font-size: 12pt;
            font-weight: 700;
            color: {{ $ink }};
            letter-spacing: 0.5px;
        }

        /* ---------- Company line ---------- */
        .company-line {
            border-top: 1px solid {{ $rule }};
            border-bottom: 1px solid {{ $rule }};
            padding: 4px 0;
            font-size: 8pt;
            color: {{ $ink }};
            margin-bottom: 14px;
        }
        .company-line strong { font-weight: 700; }
        .company-line .meta-sep { color: {{ $rule }}; margin: 0 5px; }

        /* ---------- Bill-to + metadata ---------- */
        .info { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .info .info-left  { vertical-align: top; width: 50%; }
        .info .info-right { vertical-align: top; width: 50%; text-align: right; }
        .info-label {
            font-size: 7.5pt;
            font-weight: 700;
            color: {{ $ink }};
            letter-spacing: 0.4px;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .info-body { font-size: 9pt; line-height: 1.4; }
        .info-body .muted { color: {{ $muted }}; }

        /* mpdf table-layout: the meta-table sits inside info-right
           which is a fixed-width 50% cell. With width:auto the inner
           table collapses to its content width and mpdf then squashes
           the cells into vertical character stacks. Force the inner
           table to fill its parent cell. */
        .meta-table { width: 100%; }
        .meta-table td {
            font-size: 8.5pt;
            padding: 1px 0;
            white-space: nowrap;
        }
        .meta-table td.k { color: {{ $muted }}; text-align: right; padding-right: 14px; width: 55%; }
        .meta-table td.v { font-weight: 700; text-align: right; }

        .status-line { margin-top: 6px; }
        .status-pill {
            display: inline-block;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 1px 6px;
            color: {{ $statusInfo['fg'] }};
            background: {{ $statusInfo['bg'] }};
        }

        /* ---------- Items table ---------- */
        .items { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .items thead th {
            background: {{ $faint }};
            color: {{ $ink }};
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.4px;
            padding: 6px 8px;
            text-align: left;
            text-transform: uppercase;
            border-top: 1px solid {{ $rule }};
            border-bottom: 1px solid {{ $rule }};
        }
        .items thead th.right { text-align: right; }
        .items tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid {{ $rule }};
            vertical-align: top;
            font-size: 8.5pt;
        }
        .items tbody td.right { text-align: right; }
        .items .thumb {
            width: 28px; height: 28px;
            object-fit: cover;
            border: 1px solid {{ $rule }};
        }
        .items .thumb-empty {
            width: 28px; height: 28px;
            background: {{ $faint }};
            border: 1px dashed {{ $rule }};
        }
        .items .item-name { font-weight: 700; color: {{ $ink }}; }
        .items .item-meta { color: {{ $muted }}; font-size: 7.5pt; margin-top: 1px; }
        .items .ccy-suffix { color: {{ $muted }}; font-size: 7.5pt; margin-left: 2px; }

        /* ---------- Totals (right-aligned, two-row, second row dark) ---------- */
        .totals-wrap { display: table; width: 100%; margin-top: 10px; margin-bottom: 14px; }
        .totals-spacer { display: table-cell; width: 50%; }
        .totals { display: table-cell; width: 50%; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 10px; font-size: 8.5pt; }
        .totals .label { background: {{ $faint }}; color: {{ $ink }}; font-weight: 700; }
        .totals .value { background: {{ $faint }}; text-align: right; }
        .totals .grand-row .label,
        .totals .grand-row .value {
            background: {{ $ink }};
            color: #ffffff;
            font-size: 9.5pt;
            font-weight: 700;
            letter-spacing: 0.4px;
        }
        .totals .grand-row .value { text-align: right; }

        /* ---------- Section headers ---------- */
        .section-h {
            font-size: 8pt;
            font-weight: 700;
            color: {{ $ink }};
            margin: 12px 0 5px;
            padding-bottom: 3px;
            border-bottom: 1px solid {{ $rule }};
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        /* ---------- Payment schedule (similar to items, lighter) ---------- */
        .schedule { width: 100%; border-collapse: collapse; }
        .schedule thead th {
            background: {{ $faint }};
            color: {{ $ink }};
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.4px;
            padding: 4px 8px;
            text-align: left;
            text-transform: uppercase;
            border-bottom: 1px solid {{ $rule }};
        }
        .schedule thead th.right { text-align: right; }
        .schedule tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid {{ $rule }};
            font-size: 8pt;
        }
        .schedule tbody tr:last-child td { border-bottom: none; }
        .schedule td.right { text-align: right; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .badge-paid       { background: #d1fae5; color: #065f46; }
        .badge-partial    { background: #fef3c7; color: #92400e; }
        .badge-canceled   { background: #fee2e2; color: #991b1b; }
        .badge-scheduled  { background: #e5e7eb; color: #374151; }

        /* ---------- Documents + terms ---------- */
        .docs-list { font-size: 8pt; color: #374151; padding-inline-start: 14px; margin: 0; }
        .docs-list li { margin-bottom: 2px; }
        .terms-box {
            font-size: 8pt;
            color: #374151;
            white-space: pre-wrap;
            padding: 6px 10px;
            background: {{ $faint }};
            border-left: 2px solid {{ $ink }};
            line-height: 1.4;
        }

        /* ---------- Signature ---------- */
        .signature-row { display: table; width: 100%; margin-top: 18px; }
        .signature-spacer { display: table-cell; width: 55%; }
        .signature { display: table-cell; width: 45%; vertical-align: bottom; }
        .signature-label {
            font-size: 7.5pt;
            color: {{ $muted }};
            letter-spacing: 0.3px;
            margin-bottom: 18px;
        }
        .signature-line {
            border-top: 1px solid {{ $ink }};
            padding-top: 3px;
            font-size: 7pt;
            color: {{ $muted }};
            text-align: left;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        /* ---------- Footer band ---------- */
        .footer {
            border-top: 1px solid {{ $rule }};
            margin-top: 16px;
            padding-top: 6px;
            font-size: 7.5pt;
            color: {{ $ink }};
            text-align: center;
        }
        .footer .muted { color: {{ $muted }}; }
        .footer .sep { color: {{ $rule }}; margin: 0 6px; }
    </style>
</head>
<body>

    {{-- Header. Real <table> markup (not display:table on divs) so
         mpdf renders title and logo on the SAME row instead of
         stacking them. --}}
    <table class="header" cellpadding="0" cellspacing="0">
        <tr>
            <td class="h-left">
                <div class="doc-title">PRO FORMA INVOICE</div>
            </td>
            <td class="h-right">
                @if ($logoPath)
                    {{-- mpdf ignores CSS max-width on <img>; use HTML
                         width= attribute which it always honors. --}}
                    <img src="{{ $logoPath }}" width="100" style="max-height:50px;">
                @else
                    <div class="brand-text">{{ $settings['company_name'] ?? 'Company' }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Company strip --}}
    <div class="company-line">
        <strong>{{ $settings['company_name'] ?? '' }}</strong>
        @if (!empty($settings['address']))<span class="meta-sep">·</span>{{ $settings['address'] }}@endif
        @if (!empty($settings['phone']))<span class="meta-sep">·</span>{{ $settings['phone'] }}@endif
        @if (!empty($settings['email']))<span class="meta-sep">·</span>{{ $settings['email'] }}@endif
    </div>

    {{-- BILL TO + metadata. Real <table> markup (same reason as header). --}}
    <table class="info" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-left">
                <div class="info-label">BILL TO</div>
                <div class="info-body">
                    <div style="font-weight:700;">{{ $client->name ?? '—' }}</div>
                    @if (!empty($client->code))<div class="muted">Client code: {{ $client->code }}</div>@endif
                    @if (!empty($client->phone))<div class="muted">{{ $client->phone }}</div>@endif
                    @if (!empty($client->email))<div class="muted">{{ $client->email }}</div>@endif
                </div>

                @if ($req->title)
                    <div style="margin-top:10px;">
                        <div class="info-label">SUBJECT</div>
                        <div class="info-body">
                            <div style="font-weight:700;">{{ $req->title }}</div>
                            @if ($req->description)<div class="muted">{{ $req->description }}</div>@endif
                        </div>
                    </div>
                @endif
            </td>
            <td class="info-right">
                <table class="meta-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="k">Pro forma No.:</td>
                        <td class="v">{{ $req->request_number }}</td>
                    </tr>
                    <tr>
                        <td class="k">Issue date:</td>
                        <td class="v">{{ $req->sent_at ? substr($req->sent_at, 0, 10) : date('Y-m-d') }}</td>
                    </tr>
                    @if ($req->share_token_expires_at)
                        <tr>
                            <td class="k">Valid until:</td>
                            <td class="v">{{ substr($req->share_token_expires_at, 0, 10) }}</td>
                        </tr>
                    @endif
                    @if ($req->fx_frozen_on)
                        <tr>
                            <td class="k">FX frozen on:</td>
                            <td class="v">{{ $req->fx_frozen_on }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="k">Currency:</td>
                        <td class="v">{{ $displayCcy }}</td>
                    </tr>
                </table>
                <div class="status-line">
                    <span class="status-pill">{{ $statusInfo['label'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:7%;"></th>
                <th>DESCRIPTION</th>
                <th class="right" style="width:10%;">QUANTITY</th>
                <th class="right" style="width:14%;">UNIT PRICE ({{ $displayCcy }})</th>
                <th class="right" style="width:14%;">AMOUNT ({{ $displayCcy }})</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($items as $it)
            @php
                $primary  = ($photos[$it->id] ?? null) ? $photos[$it->id][0] : null;
                $lineDisp = $toDisplay((float) $it->quantity * (float) $it->unit_price_to_client, $it->unit_cost_currency);
                $unitDisp = $toDisplay((float) $it->unit_price_to_client, $it->unit_cost_currency);
            @endphp
            <tr>
                <td>
                    @if ($primary)
                        <img class="thumb" src="{{ public_path('storage/' . $primary->path) }}">
                    @else
                        <div class="thumb-empty"></div>
                    @endif
                </td>
                <td>
                    <div class="item-name">{{ $it->name }}</div>
                    @if ($it->code)<div class="item-meta">SKU: {{ $it->code }}</div>@endif
                    @if ($it->description)<div class="item-meta">{{ $it->description }}</div>@endif
                    @if ($it->weight_kg || $it->cbm)
                        <div class="item-meta">
                            @if ($it->weight_kg){{ number_format((float) $it->weight_kg, 2) }} kg @endif
                            @if ($it->cbm) · {{ number_format((float) $it->cbm, 3) }} CBM @endif
                        </div>
                    @endif
                </td>
                <td class="right">
                    {{ rtrim(rtrim(number_format((float) $it->quantity, 4, '.', ''), '0'), '.') }}
                    <span class="ccy-suffix">{{ $it->unit }}</span>
                </td>
                <td class="right">{{ number_format($unitDisp, 2) }}</td>
                <td class="right">{{ number_format($lineDisp, 2) }}</td>
            </tr>
        @endforeach
        @if (count($items) < 1)
            <tr><td colspan="5" style="text-align:center;color:{{ $muted }};padding:20px;">No items.</td></tr>
        @endif
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-wrap">
        <div class="totals-spacer"></div>
        <div class="totals">
            <table>
                <tr>
                    <td class="label">TOTAL ({{ $displayCcy }}):</td>
                    <td class="value">{{ number_format((float) $req->items_subtotal, 2) }}</td>
                </tr>
                @if ($req->commission_mode === 'visible_separate' && (float) $req->commission_amount > 0)
                    @php $commDisp = $toDisplay((float) $req->commission_amount, $req->commission_currency ?: $displayCcy); @endphp
                    <tr>
                        <td class="label">SERVICE COMMISSION:</td>
                        <td class="value">{{ number_format($commDisp, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand-row">
                    <td class="label">TOTAL DUE ({{ $displayCcy }})</td>
                    <td class="value">{{ number_format((float) $req->proforma_total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Payment schedule --}}
    @if (count($payments) > 0)
        <div class="section-h">Payment schedule</div>
        <table class="schedule">
            <thead>
                <tr>
                    <th style="width:6%;">#</th>
                    <th>INSTALLMENT</th>
                    <th class="right" style="width:8%;">%</th>
                    <th class="right" style="width:18%;">AMOUNT</th>
                    <th style="width:14%;">DUE DATE</th>
                    <th class="right" style="width:14%;">STATUS</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($payments as $p)
                @php
                    $badgeClass = match($p->status) {
                        'paid'     => 'badge-paid',
                        'partial'  => 'badge-partial',
                        'canceled' => 'badge-canceled',
                        default    => 'badge-scheduled',
                    };
                    $badgeLabel = match($p->status) {
                        'paid'     => 'Paid',
                        'partial'  => 'Partial',
                        'canceled' => 'Canceled',
                        default    => 'Scheduled',
                    };
                @endphp
                <tr>
                    <td>{{ $p->sequence }}</td>
                    <td>{{ $p->label }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $p->percentage, 4, '.', ''), '0'), '.') }}</td>
                    <td class="right">
                        <strong>{{ number_format((float) $p->amount, 2) }}</strong>
                        <span class="ccy-suffix">{{ strtoupper($p->currency) }}</span>
                    </td>
                    <td>{{ $p->due_date ?? '—' }}</td>
                    <td class="right"><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- Documents --}}
    @php $clientDocs = collect($documents ?? [])->where('visibility', 'client_visible')->values(); @endphp
    @if ($clientDocs->count() > 0)
        <div class="section-h">Attached documents</div>
        <ul class="docs-list">
            @foreach ($clientDocs as $d)
                <li>{{ $d->label ?: $d->original_name ?: basename($d->path) }}</li>
            @endforeach
        </ul>
    @endif

    {{-- Terms --}}
    @if (!empty($req->terms_text))
        <div class="section-h">Terms &amp; notes</div>
        <div class="terms-box">{{ $req->terms_text }}</div>
    @endif

    {{-- Signature --}}
    <div class="signature-row">
        <div class="signature-spacer"></div>
        <div class="signature">
            <div class="signature-label">Issued by, signature:</div>
            <div class="signature-line">AUTHORIZED SIGNATURE</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        @if (!empty($settings['company_name'])){{ $settings['company_name'] }}@endif
        @if (!empty($settings['address']))<span class="sep">·</span>{{ $settings['address'] }}@endif
        @if (!empty($settings['phone']))<span class="sep">·</span><span class="muted">Phone:</span> {{ $settings['phone'] }}@endif
        @if (!empty($settings['email']))<span class="sep">·</span><span class="muted">Email:</span> {{ $settings['email'] }}@endif
        @if (!empty($settings['receipt_footer']))
            <div class="muted" style="margin-top:6px;">{{ $settings['receipt_footer'] }}</div>
        @endif
    </div>

</body>
</html>
