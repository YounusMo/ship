@php
    $displayCcy = strtoupper($req->display_currency ?: $req->currency ?: 'usd');
    $rates = !empty($req->fx_rate_snapshot)
        ? (json_decode($req->fx_rate_snapshot, true) ?: [])
        : $data->currency_exchange_rates;

    // Helper closures so the template stays readable.
    $toDisplay = function ($amount, $ccy) use ($displayCcy, $rates) {
        $ccy = strtolower((string) $ccy);
        $disp = strtolower($displayCcy);
        if ($ccy === $disp) return (float) $amount;
        $usd = $ccy === 'usd' ? (float) $amount : ((float) ($rates[$ccy] ?? 0) > 0 ? (float) $amount / (float) $rates[$ccy] : 0);
        return $disp === 'usd' ? $usd : $usd * (float) ($rates[$disp] ?? 1);
    };

    $logoPath = \App\Http\Controllers\settingsController::brandLogoPath();

    // Status badge — drives the colored pill at the top-right.
    $statusMap = [
        'quoted'    => ['label' => 'DRAFT',     'bg' => '#94a3b8'],
        'accepted'  => ['label' => 'APPROVED',  'bg' => '#10b981'],
        'fulfilled' => ['label' => 'FULFILLED', 'bg' => '#3b82f6'],
        'canceled'  => ['label' => 'CANCELED',  'bg' => '#ef4444'],
        'open'      => ['label' => 'OPEN',      'bg' => '#0ea5e9'],
        'searching' => ['label' => 'SEARCHING', 'bg' => '#0ea5e9'],
    ];
    $statusInfo = $statusMap[$req->status] ?? ['label' => strtoupper($req->status ?? ''), 'bg' => '#6b7280'];

    // Brand accent — a deep navy that matches the admin theme.
    $brand        = '#0f172a';   // slate-900
    $brandAccent  = '#1e293b';   // slate-800
    $brandSoft    = '#f1f5f9';   // slate-100
    $muted        = '#64748b';   // slate-500
    $border       = '#e2e8f0';   // slate-200
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma {{ $req->request_number }}</title>
    <style>
        /* ----- base ----- */
        @page { margin: 0; }
        body {
            font-family: dejavusans, sans-serif;
            color: #0f172a;
            font-size: 10.5pt;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .page { padding: 0 36px 36px; }

        /* ----- brand band ----- */
        .brand-band {
            background: {{ $brand }};
            color: #f8fafc;
            padding: 22px 36px;
            margin-bottom: 24px;
        }
        .brand-band .brand-row { display: table; width: 100%; }
        .brand-band .brand-left, .brand-band .brand-right {
            display: table-cell;
            vertical-align: middle;
        }
        .brand-band .brand-right { text-align: right; }
        .brand-band .company-name {
            font-size: 16pt;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .brand-band .company-meta {
            font-size: 8.5pt;
            color: #cbd5e1;
            margin-top: 4px;
            line-height: 1.5;
        }
        .brand-band .doc-label {
            display: inline-block;
            background: #f8fafc;
            color: {{ $brand }};
            padding: 4px 14px;
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: 1.5px;
            border-radius: 2px;
        }
        .brand-band .doc-number {
            font-size: 14pt;
            font-weight: 700;
            margin-top: 8px;
        }
        .brand-band .doc-date {
            font-size: 8.5pt;
            color: #cbd5e1;
            margin-top: 2px;
        }

        /* ----- status pill ----- */
        .status-pill {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.8px;
            color: #fff;
            background: {{ $statusInfo['bg'] }};
            margin-top: 6px;
        }

        /* ----- party cards (bill-to / subject) ----- */
        .parties { display: table; width: 100%; margin-bottom: 22px; }
        .parties .col { display: table-cell; vertical-align: top; width: 50%; padding: 0 6px; }
        .parties .card {
            border: 1px solid {{ $border }};
            border-radius: 6px;
            padding: 14px 16px;
            min-height: 100px;
        }
        .parties .card-label {
            font-size: 7.5pt;
            color: {{ $muted }};
            letter-spacing: 1.2px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .parties .card-name {
            font-size: 11.5pt;
            font-weight: 700;
            color: {{ $brand }};
        }
        .parties .card-detail {
            font-size: 9pt;
            color: {{ $muted }};
            margin-top: 4px;
            line-height: 1.6;
        }

        /* ----- items table ----- */
        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border: 1px solid {{ $border }};
            border-radius: 6px;
            overflow: hidden;
        }
        .items thead th {
            background: {{ $brand }};
            color: #f8fafc;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 10px 12px;
            text-align: left;
            text-transform: uppercase;
        }
        .items thead th.right { text-align: right; }
        .items tbody td {
            padding: 12px;
            border-bottom: 1px solid {{ $border }};
            vertical-align: top;
        }
        .items tbody tr:nth-child(even) td { background: {{ $brandSoft }}; }
        .items tbody tr:last-child td { border-bottom: none; }
        .items .right { text-align: right; }
        .items .thumb {
            width: 48px; height: 48px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid {{ $border }};
        }
        .items .thumb-empty {
            width: 48px; height: 48px;
            border-radius: 4px;
            background: {{ $brandSoft }};
            border: 1px dashed {{ $border }};
        }
        .items .item-name {
            font-weight: 700;
            color: {{ $brand }};
            font-size: 10pt;
        }
        .items .item-meta {
            color: {{ $muted }};
            font-size: 8.5pt;
            margin-top: 3px;
        }
        .items .ccy-suffix { color: {{ $muted }}; font-size: 8.5pt; margin-left: 3px; }

        /* ----- totals block ----- */
        .totals-wrap { display: table; width: 100%; margin-top: 16px; }
        .totals-spacer { display: table-cell; width: 55%; }
        .totals {
            display: table-cell;
            width: 45%;
            border: 1px solid {{ $border }};
            border-radius: 6px;
            overflow: hidden;
        }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 10px 14px; font-size: 10pt; }
        .totals .label { color: {{ $muted }}; }
        .totals .value { text-align: right; font-weight: 600; }
        .totals .grand-row { background: {{ $brand }}; }
        .totals .grand-row .label,
        .totals .grand-row .value {
            color: #f8fafc;
            font-size: 12pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        /* ----- section headers ----- */
        .section-h {
            font-size: 11pt;
            font-weight: 700;
            color: {{ $brand }};
            margin: 26px 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid {{ $brand }};
            letter-spacing: 0.3px;
        }

        /* ----- payment schedule ----- */
        .schedule { width: 100%; border-collapse: collapse; }
        .schedule thead th {
            background: {{ $brandSoft }};
            color: {{ $brand }};
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: 0.8px;
            padding: 8px 10px;
            text-align: left;
            text-transform: uppercase;
            border-bottom: 2px solid {{ $border }};
        }
        .schedule thead th.right { text-align: right; }
        .schedule tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid {{ $border }};
            font-size: 9.5pt;
        }
        .schedule tbody tr:last-child td { border-bottom: none; }
        .schedule .right { text-align: right; }
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .badge-paid       { background: #d1fae5; color: #047857; }
        .badge-partial    { background: #fef3c7; color: #92400e; }
        .badge-canceled   { background: #fee2e2; color: #991b1b; }
        .badge-scheduled  { background: #e2e8f0; color: #475569; }

        /* ----- documents + terms ----- */
        .docs-list {
            font-size: 9.5pt;
            color: #334155;
            padding-inline-start: 18px;
            margin: 0;
        }
        .docs-list li { margin-bottom: 4px; }
        .terms-box {
            font-size: 9.5pt;
            color: #334155;
            white-space: pre-wrap;
            padding: 14px 18px;
            background: {{ $brandSoft }};
            border-left: 4px solid {{ $brand }};
            border-radius: 0 4px 4px 0;
            line-height: 1.6;
        }

        /* ----- signature block ----- */
        .signatures { display: table; width: 100%; margin-top: 40px; }
        .signatures .sig {
            display: table-cell;
            width: 50%;
            padding: 0 10px;
            vertical-align: bottom;
        }
        .signatures .sig-line {
            border-top: 1.5px solid {{ $brand }};
            padding-top: 6px;
            font-size: 8.5pt;
            color: {{ $muted }};
            letter-spacing: 0.5px;
            text-align: center;
        }

        /* ----- footer ----- */
        .footer {
            margin-top: 30px;
            padding-top: 14px;
            border-top: 1px solid {{ $border }};
            font-size: 8pt;
            color: {{ $muted }};
            text-align: center;
            line-height: 1.6;
        }
    </style>
</head>
<body>

    {{-- Brand band --}}
    <div class="brand-band">
        <div class="brand-row">
            <div class="brand-left">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" style="max-height:48px;max-width:170px;">
                @endif
                <div class="company-name">{{ $settings['company_name'] ?? 'Company' }}</div>
                <div class="company-meta">
                    @if (!empty($settings['address'])) {{ $settings['address'] }}<br> @endif
                    @if (!empty($settings['phone'])) {{ $settings['phone'] }} @endif
                    @if (!empty($settings['phone']) && !empty($settings['email'])) · @endif
                    @if (!empty($settings['email'])) {{ $settings['email'] }} @endif
                    @if (!empty($settings['commercial_registry']) || !empty($settings['tax_id']))
                        <br>
                        @if (!empty($settings['commercial_registry']))Reg: {{ $settings['commercial_registry'] }}@endif
                        @if (!empty($settings['commercial_registry']) && !empty($settings['tax_id'])) · @endif
                        @if (!empty($settings['tax_id']))Tax ID: {{ $settings['tax_id'] }}@endif
                    @endif
                </div>
            </div>
            <div class="brand-right">
                <span class="doc-label">PROFORMA INVOICE</span>
                <div class="doc-number">{{ $req->request_number }}</div>
                <div class="doc-date">
                    {{ $req->sent_at ? substr($req->sent_at, 0, 10) : date('Y-m-d') }}
                    @if ($req->share_token_expires_at)
                        · Valid until {{ substr($req->share_token_expires_at, 0, 10) }}
                    @endif
                </div>
                <span class="status-pill">{{ $statusInfo['label'] }}</span>
            </div>
        </div>
    </div>

    <div class="page">

        {{-- Bill to + Subject --}}
        <div class="parties">
            <div class="col">
                <div class="card">
                    <div class="card-label">BILL TO</div>
                    <div class="card-name">{{ $client->name ?? '—' }}</div>
                    <div class="card-detail">
                        @if (!empty($client->code))Client code: {{ $client->code }}<br>@endif
                        @if (!empty($client->phone)){{ $client->phone }}<br>@endif
                        @if (!empty($client->email)){{ $client->email }}@endif
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="card-label">SUBJECT</div>
                    <div class="card-name">{{ $req->title }}</div>
                    @if ($req->description)
                        <div class="card-detail">{{ $req->description }}</div>
                    @endif
                    @if ($req->fx_frozen_on)
                        <div class="card-detail" style="margin-top:8px;font-size:8.5pt;">
                            FX rates frozen on {{ $req->fx_frozen_on }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Items --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width:8%;"></th>
                    <th>PRODUCT</th>
                    <th class="right" style="width:9%;">QTY</th>
                    <th class="right" style="width:14%;">UNIT PRICE</th>
                    <th class="right" style="width:14%;">TOTAL</th>
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
                    <td class="right">
                        {{ number_format($unitDisp, 2) }}
                        <span class="ccy-suffix">{{ $displayCcy }}</span>
                    </td>
                    <td class="right">
                        <strong>{{ number_format($lineDisp, 2) }}</strong>
                        <span class="ccy-suffix">{{ $displayCcy }}</span>
                    </td>
                </tr>
            @endforeach
            @if (count($items) < 1)
                <tr><td colspan="5" style="text-align:center;color:{{ $muted }};padding:24px;">No items.</td></tr>
            @endif
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals-wrap">
            <div class="totals-spacer"></div>
            <div class="totals">
                <table>
                    <tr>
                        <td class="label">Items subtotal</td>
                        <td class="value">{{ number_format((float) $req->items_subtotal, 2) }} {{ $displayCcy }}</td>
                    </tr>
                    @if ($req->commission_mode === 'visible_separate' && (float) $req->commission_amount > 0)
                        @php $commDisp = $toDisplay((float) $req->commission_amount, $req->commission_currency ?: $displayCcy); @endphp
                        <tr>
                            <td class="label">Service commission</td>
                            <td class="value">{{ number_format($commDisp, 2) }} {{ $displayCcy }}</td>
                        </tr>
                    @endif
                    <tr class="grand-row">
                        <td class="label">TOTAL</td>
                        <td class="value">{{ number_format((float) $req->proforma_total, 2) }} {{ $displayCcy }}</td>
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

        {{-- Documents (client-visible only) --}}
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

        {{-- Signature block --}}
        <div class="signatures">
            <div class="sig">
                <div style="height:30px;"></div>
                <div class="sig-line">CLIENT APPROVAL</div>
            </div>
            <div class="sig">
                <div style="height:30px;"></div>
                <div class="sig-line">AUTHORIZED SIGNATURE</div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            {{ $settings['receipt_footer'] ?? '' }}
            @if (!empty($settings['company_name']))
                <br>{{ $settings['company_name'] }} — generated {{ now()->format('Y-m-d H:i') }}
            @endif
        </div>

    </div>

</body>
</html>
