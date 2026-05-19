@php
    $kindLabel = match ($receipt->kind) {
        'deposit'           => $lang->write('Receipt of Deposit'),
        'withdraw'          => $lang->write('Receipt of Withdrawal'),
        'commission'        => $lang->write('Receipt of Commission'),
        'transfer'          => $lang->write('Receipt of Currency Transfer'),
        'transfer_in'       => $lang->write('Receipt — Transfer In'),
        'transfer_out'      => $lang->write('Receipt — Transfer Out'),
        'supplier_deposit'  => $lang->write('Receipt — Supplier'),
        'customs_deposit'   => $lang->write('Receipt — Customs'),
        default             => $lang->write('Receipt'),
    };

    $currencySymbol = $data->get_cur($receipt->currency ?? 'usd', 'symbol');
    $amountFormatted = number_format(floatval($receipt->amount ?? 0), 2, '.', ',');
    $receiptNo = $receipt->series_letter . '-' . str_pad((string)$receipt->series_number, 6, '0', STR_PAD_LEFT);

    $brandColor = '#0e2a47';   // navy
    $accentColor = '#c9a246';  // gold
    $muted = '#5b667a';
    $border = '#e2e6ee';

    $purposeLabel = $receipt->purpose ? $data->purposeLabel($receipt->purpose) : '';
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: dejavusans, sans-serif; box-sizing: border-box; }
    body { font-size: 11px; color: #1f2a3c; margin: 0; }
    .row { display: table; width: 100%; }
    .col { display: table-cell; vertical-align: top; }
    .header {
        border-bottom: 2px solid {{ $brandColor }};
        padding-bottom: 8px;
        margin-bottom: 10px;
    }
    .header .brand {
        color: {{ $brandColor }};
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .header .brand-mark {
        display: inline-block;
        width: 28px;
        height: 28px;
        background: {{ $brandColor }};
        color: {{ $accentColor }};
        text-align: center;
        font-size: 16px;
        font-weight: 700;
        line-height: 28px;
        border-radius: 4px;
        margin-right: 6px;
        vertical-align: middle;
    }
    .header .sub {
        color: {{ $muted }};
        font-size: 9px;
        margin-top: 2px;
    }
    .header .legal {
        color: {{ $muted }};
        font-size: 8px;
        text-align: right;
        line-height: 1.4;
    }
    .receipt-title {
        background: {{ $brandColor }};
        color: white;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
        border-radius: 3px;
    }
    .receipt-title .no {
        float: right;
        background: {{ $accentColor }};
        color: {{ $brandColor }};
        padding: 1px 8px;
        border-radius: 3px;
        font-size: 12px;
    }
    .panel {
        border: 1px solid {{ $border }};
        border-radius: 3px;
        padding: 8px 10px;
        margin-bottom: 8px;
    }
    .panel .label {
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: {{ $muted }};
        margin-bottom: 2px;
    }
    .panel .value {
        font-size: 11px;
        color: #0c1729;
        font-weight: 600;
    }
    .amount-box {
        background: #fafbfc;
        border: 1px solid {{ $border }};
        padding: 10px;
        margin: 8px 0;
        text-align: center;
        border-radius: 3px;
    }
    .amount-box .amount {
        font-size: 24px;
        font-weight: 700;
        color: {{ $brandColor }};
    }
    .amount-box .amount-label {
        font-size: 9px;
        color: {{ $muted }};
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 4px;
    }
    .meta-table { width: 100%; border-collapse: collapse; margin: 4px 0; }
    .meta-table td { padding: 3px 6px; font-size: 10px; vertical-align: top; }
    .meta-table .k { color: {{ $muted }}; width: 30%; }
    .meta-table .v { color: #0c1729; font-weight: 600; }
    .signature-row { margin-top: 18px; }
    .signature-row .col { width: 50%; padding: 6px 4px; }
    .signature-row .line {
        border-top: 1px solid #344054;
        padding-top: 4px;
        text-align: center;
        font-size: 9px;
        color: {{ $muted }};
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .footer {
        margin-top: 12px;
        padding-top: 6px;
        border-top: 1px dashed {{ $border }};
        text-align: center;
        color: {{ $muted }};
        font-size: 8px;
    }
    .voided-overlay {
        position: fixed;
        top: 40%;
        left: 0;
        width: 100%;
        text-align: center;
        font-size: 60px;
        color: rgba(184, 58, 46, 0.25);
        font-weight: 900;
        transform: rotate(-18deg);
        pointer-events: none;
    }
</style>
</head>
<body>

@if ($receipt->voided)
    <div class="voided-overlay">VOID</div>
@endif

<div class="header row">
    <div class="col" style="width: 60%">
        @include('partials.brand_mark_pdf', ['settings' => $settings, 'brandColor' => $brandColor, 'accentColor' => $accentColor, 'size' => 28])
        <span class="brand">{{ $settings['company_name'] ?? '' }}</span>
        <div class="sub">
            {{ $settings['address'] ?? '' }}
            @if (!empty($settings['phone'])) &middot; {{ $settings['phone'] }} @endif
            @if (!empty($settings['email'])) &middot; {{ $settings['email'] }} @endif
        </div>
    </div>
    <div class="col legal" style="width: 40%">
        @if (!empty($settings['commercial_registry']))
            <div>{{ $lang->write('Commercial registry') }}: <strong>{{ $settings['commercial_registry'] }}</strong></div>
        @endif
        @if (!empty($settings['tax_id']))
            <div>{{ $lang->write('Tax ID') }}: <strong>{{ $settings['tax_id'] }}</strong></div>
        @endif
        <div style="margin-top: 4px;">
            {{ $receipt->issued_at }}
        </div>
    </div>
</div>

<div class="receipt-title">
    {{ $kindLabel }}
    <span class="no">#{{ $receiptNo }}</span>
</div>

<div class="row">
    <div class="col" style="width: 50%; padding-right: 4px;">
        <div class="panel">
            <div class="label">{{ $lang->write('Counterparty') }}</div>
            <div class="value">
                {{ $receipt->counterparty_label ?? '—' }}
                @if (!empty($receipt->counterparty_code))
                    <span style="color: {{ $muted }}; font-weight: 500;">({{ $receipt->counterparty_code }})</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col" style="width: 50%; padding-left: 4px;">
        <div class="panel">
            <div class="label">{{ $lang->write('Branch / Treasury') }}</div>
            <div class="value">
                {{ $branch ? $lang->branch($branch->id) : '—' }}
            </div>
        </div>
    </div>
</div>

<div class="amount-box">
    <div class="amount-label">{{ $lang->write('Amount') }}</div>
    <div class="amount">
        {{ $amountFormatted }} <span style="font-size: 14px; color: {{ $accentColor }};">{{ $currencySymbol }}</span>
    </div>
    <div style="font-size: 9px; color: {{ $muted }}; margin-top: 2px;">
        {{ strtoupper($receipt->currency ?? '') }}
    </div>
</div>

<table class="meta-table">
    @if ($purposeLabel)
        <tr>
            <td class="k">{{ $lang->write('Purpose') }}</td>
            <td class="v">{{ $purposeLabel }}</td>
        </tr>
    @endif
    @if (!empty($receipt->notes))
        <tr>
            <td class="k">{{ $lang->write('Notes') }}</td>
            <td class="v" style="white-space: pre-line;">{{ $receipt->notes }}</td>
        </tr>
    @endif
    <tr>
        <td class="k">{{ $lang->write('Issued by') }}</td>
        <td class="v">{{ $receipt->issued_by_user_name ?? '—' }}</td>
    </tr>
    @if (!empty($receipt->transaction_number))
        <tr>
            <td class="k">{{ $lang->write('Internal ref') }}</td>
            <td class="v" style="font-family: monospace; font-size: 9px;">{{ $receipt->transaction_number }}</td>
        </tr>
    @endif
</table>

<div class="signature-row row">
    <div class="col">
        <div class="line">{{ $lang->write('Cashier signature & stamp') }}</div>
    </div>
    <div class="col">
        <div class="line">{{ $lang->write('Counterparty signature') }}</div>
    </div>
</div>

@if (!empty($settings['receipt_footer']))
    <div class="footer">{{ $settings['receipt_footer'] }}</div>
@endif

@if ($receipt->voided)
    <div class="footer" style="color: #962e25; font-weight: 600;">
        {{ $lang->write('This receipt was VOIDED on') }} {{ $receipt->voided_at }}
        @if (!empty($receipt->void_reason)) — {{ $receipt->void_reason }} @endif
    </div>
@endif

</body>
</html>
