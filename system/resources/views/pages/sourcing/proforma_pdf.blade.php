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
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma {{ $req->request_number }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; color: #1f2937; font-size: 11pt; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header .left, .header .right { display: table-cell; vertical-align: top; }
        .header .right { text-align: right; }
        .brand { font-size: 18pt; font-weight: 700; color: #0f172a; }
        .muted { color: #6b7280; font-size: 9pt; }
        .doc-title { font-size: 14pt; font-weight: 700; color: #1f2937; letter-spacing: 1px; }
        .doc-meta  { font-size: 9pt; color: #6b7280; line-height: 1.55; }
        .panel { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; margin-bottom: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 6px 8px; vertical-align: top; }
        thead th { background: #f3f4f6; border-bottom: 2px solid #d1d5db; font-size: 9pt; text-align: left; color: #374151; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:last-child { border-bottom: none; }
        .right { text-align: right; }
        .center { text-align: center; }
        .thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #e5e7eb; }
        .totals { width: 280px; margin-left: auto; margin-top: 10px; }
        .totals td { font-size: 10.5pt; padding: 4px 6px; }
        .totals .label { color: #6b7280; }
        .totals .value { text-align: right; font-weight: 600; }
        .totals .grand { border-top: 2px solid #111827; font-size: 12pt; font-weight: 700; color: #047857; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8.5pt; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: #e5e7eb; color: #374151; }
        .terms { font-size: 9.5pt; color: #4b5563; white-space: pre-wrap; padding: 10px; background: #f9fafb; border-left: 3px solid #94a3b8; }
        h2 { font-size: 11pt; color: #0f172a; margin: 14px 0 6px; }
        .footer { margin-top: 24px; font-size: 8.5pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <div class="left">
            @if ($logoPath)
                <img src="{{ $logoPath }}" style="max-height:54px;max-width:200px;">
            @else
                <div class="brand">{{ $settings['company_name'] ?? 'Company' }}</div>
            @endif
            <div class="muted">
                {{ $settings['address'] ?? '' }}<br>
                {{ $settings['phone'] ?? '' }} · {{ $settings['email'] ?? '' }}<br>
                @if (!empty($settings['commercial_registry']))
                    Reg: {{ $settings['commercial_registry'] }}
                @endif
                @if (!empty($settings['tax_id']))
                    · Tax ID: {{ $settings['tax_id'] }}
                @endif
            </div>
        </div>
        <div class="right">
            <div class="doc-title">PROFORMA INVOICE</div>
            <div class="doc-meta">
                <strong>No.</strong> {{ $req->request_number }}<br>
                <strong>Date:</strong> {{ $req->sent_at ? substr($req->sent_at, 0, 10) : date('Y-m-d') }}<br>
                @if ($req->fx_frozen_on)
                    <strong>FX frozen:</strong> {{ $req->fx_frozen_on }}<br>
                @endif
                @if ($req->share_token_expires_at)
                    <strong>Valid until:</strong> {{ substr($req->share_token_expires_at, 0, 10) }}
                @endif
            </div>
        </div>
    </div>

    {{-- Client + summary --}}
    <table style="margin-bottom:14px;">
        <tr>
            <td style="width:50%;border:1px solid #e5e7eb;border-radius:4px;padding:10px;">
                <div class="muted">BILL TO</div>
                <div style="font-weight:600;font-size:11pt;">{{ $client->name ?? '—' }}</div>
                <div class="muted">
                    @if (!empty($client->code)) Code: {{ $client->code }}<br> @endif
                    @if (!empty($client->phone)) {{ $client->phone }}<br> @endif
                    @if (!empty($client->email)) {{ $client->email }} @endif
                </div>
            </td>
            <td style="width:50%;padding-left:14px;">
                <div class="muted">SUBJECT</div>
                <div style="font-weight:600;font-size:11pt;">{{ $req->title }}</div>
                @if ($req->description)
                    <div class="muted" style="margin-top:4px;">{{ $req->description }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table>
        <thead>
            <tr>
                <th style="width:8%;">Photo</th>
                <th>Product</th>
                <th class="right" style="width:9%;">Qty</th>
                <th class="right" style="width:13%;">Unit price</th>
                <th class="right" style="width:13%;">Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($items as $it)
            @php
                $primary = ($photos[$it->id] ?? null) ? $photos[$it->id][0] : null;
                $lineDisp = $toDisplay((float) $it->quantity * (float) $it->unit_price_to_client, $it->unit_cost_currency);
                // When commission_mode=visible_separate we show the supplier-cost unit price; in
                // hidden_in_prices we show the marked-up price. Both are stored on the same row;
                // the operator is expected to set unit_price_to_client appropriately for the mode.
                $unitDisp = $toDisplay((float) $it->unit_price_to_client, $it->unit_cost_currency);
            @endphp
            <tr>
                <td>
                    @if ($primary)
                        <img class="thumb" src="{{ public_path('storage/' . $primary->path) }}">
                    @else
                        <div style="width:44px;height:44px;border:1px dashed #d1d5db;border-radius:4px;"></div>
                    @endif
                </td>
                <td>
                    <div style="font-weight:600;">{{ $it->name }}</div>
                    @if ($it->code)
                        <div class="muted">SKU: {{ $it->code }}</div>
                    @endif
                    @if ($it->description)
                        <div class="muted" style="margin-top:2px;">{{ $it->description }}</div>
                    @endif
                    @if ($it->weight_kg || $it->cbm)
                        <div class="muted" style="margin-top:2px;">
                            @if ($it->weight_kg) {{ number_format((float) $it->weight_kg, 2) }} kg @endif
                            @if ($it->cbm) · {{ number_format((float) $it->cbm, 3) }} CBM @endif
                        </div>
                    @endif
                </td>
                <td class="right">{{ rtrim(rtrim(number_format((float) $it->quantity, 4, '.', ''), '0'), '.') }} <span class="muted">{{ $it->unit }}</span></td>
                <td class="right">{{ number_format($unitDisp, 2) }} <span class="muted">{{ $displayCcy }}</span></td>
                <td class="right">{{ number_format($lineDisp, 2) }} <span class="muted">{{ $displayCcy }}</span></td>
            </tr>
        @endforeach
        @if (count($items) < 1)
            <tr><td colspan="5" class="muted center">No items.</td></tr>
        @endif
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td class="label">Items subtotal</td>
            <td class="value">{{ number_format((float) $req->items_subtotal, 2) }} {{ $displayCcy }}</td>
        </tr>
        @if ($req->commission_mode === 'visible_separate' && (float) $req->commission_amount > 0)
            @php
                $commDisp = $toDisplay((float) $req->commission_amount, $req->commission_currency ?: $displayCcy);
            @endphp
            <tr>
                <td class="label">Service commission</td>
                <td class="value">{{ number_format($commDisp, 2) }} {{ $displayCcy }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="label">TOTAL</td>
            <td class="value">{{ number_format((float) $req->proforma_total, 2) }} {{ $displayCcy }}</td>
        </tr>
    </table>

    {{-- Payment schedule --}}
    @if (count($payments) > 0)
        <h2>Payment schedule</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:8%;">#</th>
                    <th>Installment</th>
                    <th class="right" style="width:10%;">%</th>
                    <th class="right" style="width:16%;">Amount</th>
                    <th style="width:14%;">Due date</th>
                    <th class="right" style="width:12%;">Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($payments as $p)
                <tr>
                    <td>{{ $p->sequence }}</td>
                    <td>{{ $p->label }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $p->percentage, 4, '.', ''), '0'), '.') }}</td>
                    <td class="right">{{ number_format((float) $p->amount, 2) }} {{ strtoupper($p->currency) }}</td>
                    <td>{{ $p->due_date ?? '—' }}</td>
                    <td class="right">
                        @if ($p->status === 'paid')
                            <span class="badge badge-info">Paid</span>
                        @elseif ($p->status === 'partial')
                            <span class="badge badge-secondary">Partial</span>
                        @elseif ($p->status === 'canceled')
                            <span class="badge badge-secondary">Canceled</span>
                        @else
                            <span class="badge badge-secondary">Scheduled</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- Documents (client-visible only) --}}
    @php
        $clientDocs = collect($documents ?? [])->where('visibility', 'client_visible')->values();
    @endphp
    @if ($clientDocs->count() > 0)
        <h2>Attached documents</h2>
        <ul style="font-size:10pt;color:#4b5563;padding-inline-start:18px;">
            @foreach ($clientDocs as $d)
                <li>{{ $d->label ?: $d->original_name ?: basename($d->path) }}</li>
            @endforeach
        </ul>
    @endif

    {{-- Terms --}}
    @if (!empty($req->terms_text))
        <h2>Terms & notes</h2>
        <div class="terms">{{ $req->terms_text }}</div>
    @endif

    <div class="footer">
        {{ $settings['receipt_footer'] ?? '' }}
    </div>

</body>
</html>
