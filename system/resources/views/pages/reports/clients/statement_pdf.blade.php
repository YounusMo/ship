@php
    $brandColor  = '#0e2a47';
    $accentColor = '#c9a246';
    $muted       = '#5b667a';
    $border      = '#e2e6ee';
    $hdrBg       = '#fafbfc';

    function _fmt($v) {
        return number_format(floatval($v ?? 0), 2, '.', ',');
    }

    $currencies = ['usd', 'eur', 'den', 'cny'];
    $currencyLabel = ['usd' => 'USD', 'eur' => 'EUR', 'den' => 'LYD', 'cny' => 'CNY'];

    $typeLabel = function($t) use ($lang) {
        return match ($t) {
            'deposit'             => $lang->write('Deposit'),
            'withdraw'            => $lang->write('Withdraw'),
            'withdraw_commission' => $lang->write('Commission'),
            'transfer'            => $lang->write('Currency transfer'),
            'exp_deposit'         => $lang->write('Shipping deposit'),
            'exp_withdraw'        => $lang->write('Shipping withdraw'),
            default               => $t,
        };
    };
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: dejavusans, sans-serif; box-sizing: border-box; }
    body { font-size: 10px; color: #1f2a3c; margin: 0; }
    .row { display: table; width: 100%; }
    .col { display: table-cell; vertical-align: top; }

    .header {
        border-bottom: 2px solid {{ $brandColor }};
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .brand {
        color: {{ $brandColor }};
        font-size: 20px;
        font-weight: 700;
    }
    .brand-mark {
        display: inline-block;
        width: 30px;
        height: 30px;
        background: {{ $brandColor }};
        color: {{ $accentColor }};
        text-align: center;
        font-size: 18px;
        font-weight: 700;
        line-height: 30px;
        border-radius: 4px;
        margin-right: 8px;
        vertical-align: middle;
    }
    .sub { color: {{ $muted }}; font-size: 9px; margin-top: 2px; }
    .legal { color: {{ $muted }}; font-size: 8px; text-align: right; line-height: 1.4; }

    .title {
        background: {{ $brandColor }};
        color: white;
        padding: 8px 12px;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 12px;
        border-radius: 3px;
    }
    .title .period {
        float: right;
        background: {{ $accentColor }};
        color: {{ $brandColor }};
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
    }

    .info-grid {
        margin-bottom: 14px;
    }
    .info-grid .col {
        padding: 8px 10px;
        border: 1px solid {{ $border }};
        border-radius: 3px;
    }
    .info-grid .col + .col {
        border-left: none;
        border-radius: 0 3px 3px 0;
    }
    .info-grid .col:first-child {
        border-radius: 3px 0 0 3px;
    }
    .info-label {
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: {{ $muted }};
        margin-bottom: 2px;
    }
    .info-value {
        font-size: 11px;
        color: #0c1729;
        font-weight: 600;
    }

    .opening-grid {
        margin-bottom: 12px;
        border: 1px solid {{ $border }};
        border-radius: 3px;
        overflow: hidden;
    }
    .opening-grid table { width: 100%; border-collapse: collapse; }
    .opening-grid th, .opening-grid td {
        padding: 6px 8px;
        font-size: 10px;
        border-bottom: 1px solid {{ $border }};
    }
    .opening-grid th {
        background: {{ $brandColor }};
        color: white;
        text-align: left;
        font-weight: 600;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .opening-grid td.amt {
        text-align: right;
        font-family: dejavusans;
    }
    .opening-grid tr:last-child td { border-bottom: none; }

    table.txns {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    table.txns th {
        background: {{ $brandColor }};
        color: white;
        font-size: 9px;
        text-transform: uppercase;
        padding: 6px 6px;
        letter-spacing: 0.4px;
        text-align: left;
    }
    table.txns th.r, table.txns td.r { text-align: right; }
    table.txns td {
        padding: 5px 6px;
        border-bottom: 1px solid {{ $border }};
        font-size: 9.5px;
        vertical-align: top;
    }
    table.txns tr:nth-child(even) td { background: {{ $hdrBg }}; }
    .badge {
        display: inline-block;
        font-size: 8px;
        padding: 1px 5px;
        border-radius: 2px;
        background: #e3ebf3;
        color: {{ $brandColor }};
        font-weight: 600;
    }
    .empty {
        padding: 20px;
        text-align: center;
        color: {{ $muted }};
        border: 1px dashed {{ $border }};
        border-radius: 3px;
        margin-bottom: 12px;
    }

    .signature-row { margin-top: 22px; }
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
        margin-top: 10px;
        padding-top: 6px;
        border-top: 1px dashed {{ $border }};
        text-align: center;
        color: {{ $muted }};
        font-size: 8px;
    }
</style>
</head>
<body>

<div class="header row">
    <div class="col" style="width: 60%">
        @include('partials.brand_mark_pdf', ['settings' => $settings, 'brandColor' => $brandColor, 'accentColor' => $accentColor, 'size' => 30])
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
        <div style="margin-top: 4px;">{{ $lang->write('Issued') }}: {{ date('Y-m-d H:i') }}</div>
    </div>
</div>

<div class="title">
    {{ $lang->write('Statement of Account') }}
    <span class="period">{{ $from }} → {{ $to }}</span>
</div>

<div class="info-grid row">
    <div class="col" style="width: 33.33%">
        <div class="info-label">{{ $lang->write('Client') }}</div>
        <div class="info-value">
            {{ $client->name }}
            @if (!empty($client->code))
                <span style="color: {{ $muted }}; font-weight: 500;">({{ $client->code }})</span>
            @endif
        </div>
    </div>
    <div class="col" style="width: 33.33%">
        <div class="info-label">{{ $lang->write('Branch') }}</div>
        <div class="info-value">{{ $branchName ?: '—' }}</div>
    </div>
    <div class="col" style="width: 33.33%">
        <div class="info-label">{{ $lang->write('Contact') }}</div>
        <div class="info-value">
            {{ $client->phone ?: '—' }}
            @if (!empty($client->email))
                <div style="font-size:9px;font-weight:500;color:{{ $muted }}">{{ $client->email }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Opening balance ------------------------------------------------------- --}}
<div class="opening-grid">
    <table>
        <thead>
            <tr>
                <th style="width:55%">{{ $lang->write('Opening balance') }} @ {{ $from }}</th>
                @foreach ($currencies as $c)
                    <th class="r" style="width:11.25%">{{ $currencyLabel[$c] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $lang->write('Carried forward from prior periods') }}</td>
                @foreach ($currencies as $c)
                    <td class="amt" style="color: {{ ($openingPerCcy[$c] ?? 0) < 0 ? '#962e25' : '#0c1729' }}">
                        {{ _fmt($openingPerCcy[$c] ?? 0) }}
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>

{{-- Transactions ----------------------------------------------------------- --}}
@if (count($rows) > 0)
    <table class="txns">
        <thead>
            <tr>
                <th style="width: 8%">{{ $lang->write('Date') }}</th>
                <th style="width: 13%">{{ $lang->write('Type') }}</th>
                <th style="width: 32%">{{ $lang->write('Notes / Purpose') }}</th>
                <th style="width: 7%">{{ $lang->write('CCY') }}</th>
                <th class="r" style="width: 13%">{{ $lang->write('Debit') }}</th>
                <th class="r" style="width: 13%">{{ $lang->write('Credit') }}</th>
                <th class="r" style="width: 14%">{{ $lang->write('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $typeLabel($row['type']) }}</td>
                    <td>
                        @if (!empty($row['purpose']))
                            <span class="badge">{{ $data->purposeLabel($row['purpose']) }}</span>
                        @endif
                        {{ $row['notes'] }}
                    </td>
                    <td>{{ strtoupper($row['currency']) }}</td>
                    <td class="r" style="color:#962e25">{{ $row['debit'] !== null ? _fmt($row['debit']) : '—' }}</td>
                    <td class="r" style="color:#156236">{{ $row['credit'] !== null ? _fmt($row['credit']) : '—' }}</td>
                    <td class="r" style="font-weight:600; color: {{ $row['balance'] < 0 ? '#962e25' : '#0c1729' }}">
                        {{ _fmt($row['balance']) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <div class="empty">{{ $lang->write('No transactions in this period.') }}</div>
@endif

{{-- Closing balance ------------------------------------------------------- --}}
<div class="opening-grid">
    <table>
        <thead>
            <tr>
                <th style="width:55%">{{ $lang->write('Closing balance') }} @ {{ $to }}</th>
                @foreach ($currencies as $c)
                    <th class="r" style="width:11.25%">{{ $currencyLabel[$c] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $lang->write('Net position at period end') }}</td>
                @foreach ($currencies as $c)
                    <td class="amt" style="font-weight:700; color: {{ ($closingPerCcy[$c] ?? 0) < 0 ? '#962e25' : '#156236' }}">
                        {{ _fmt($closingPerCcy[$c] ?? 0) }}
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>

<div class="signature-row row">
    <div class="col"><div class="line">{{ $lang->write('Authorized signature & stamp') }}</div></div>
    <div class="col"><div class="line">{{ $lang->write('Client acknowledgement') }}</div></div>
</div>

@if (!empty($settings['receipt_footer']))
    <div class="footer">{{ $settings['receipt_footer'] }}</div>
@endif

</body>
</html>
