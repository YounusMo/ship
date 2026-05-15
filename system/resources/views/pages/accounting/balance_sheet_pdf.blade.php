@php
    $brandColor  = '#0e2a47';
    $accentColor = '#c9a246';
    $muted       = '#5b667a';
    $border      = '#e2e6ee';

    function _f($v) { return number_format(floatval($v ?? 0), 2, '.', ','); }
    $currencyLabel = ['usd' => 'USD', 'eur' => 'EUR', 'den' => 'LYD', 'cny' => 'CNY'];
    $currencies = ['usd', 'eur', 'den', 'cny'];
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

    .header { border-bottom: 2px solid {{ $brandColor }}; padding-bottom: 10px; margin-bottom: 14px; }
    .brand { color: {{ $brandColor }}; font-size: 20px; font-weight: 700; }
    .brand-mark { display: inline-block; width: 30px; height: 30px; background: {{ $brandColor }}; color: {{ $accentColor }};
        text-align: center; font-size: 18px; font-weight: 700; line-height: 30px; border-radius: 4px; margin-right: 8px; vertical-align: middle; }
    .sub   { color: {{ $muted }}; font-size: 9px; margin-top: 2px; }
    .legal { color: {{ $muted }}; font-size: 8px; text-align: right; line-height: 1.4; }

    .title { background: {{ $brandColor }}; color: white; padding: 8px 12px; font-size: 14px; font-weight: 700;
        letter-spacing: 1px; text-transform: uppercase; margin-bottom: 12px; border-radius: 3px; }
    .title .as-of { float: right; background: {{ $accentColor }}; color: {{ $brandColor }};
        padding: 2px 8px; border-radius: 3px; font-size: 11px; }

    table.figures { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.figures th, table.figures td { border: 1px solid {{ $border }}; padding: 6px 8px; }
    table.figures th { background: #fafbfc; color: {{ $muted }}; font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; }
    table.figures td.num { text-align: right; font-variant-numeric: tabular-nums; }
    table.figures tr.section td { background: {{ $brandColor }}; color: white; font-weight: 700; text-transform: uppercase; font-size: 10px; }
    table.figures tr.subtotal td { background: #f3f5f9; font-weight: 700; }
    table.figures tr.equation td { background: {{ $accentColor }}; color: {{ $brandColor }}; font-weight: 700; }

    .footer { color: {{ $muted }}; font-size: 8px; text-align: center; margin-top: 12px; }
</style>
</head>
<body>

<div class="header">
    <div class="row">
        <div class="col" style="width: 60%;">
            <span class="brand-mark">M</span>
            <span class="brand">{{ $settings['company_name'] ?? '' }}</span>
            <div class="sub">{{ $settings['address'] ?? '' }} · {{ $settings['phone'] ?? '' }} · {{ $settings['email'] ?? '' }}</div>
        </div>
        <div class="col legal" style="width: 40%;">
            @if (!empty($settings['commercial_registry']))
                <div>{{ $lang->write('Commercial Registry') }}: <strong>{{ $settings['commercial_registry'] }}</strong></div>
            @endif
            @if (!empty($settings['tax_id']))
                <div>{{ $lang->write('Tax ID') }}: <strong>{{ $settings['tax_id'] }}</strong></div>
            @endif
        </div>
    </div>
</div>

<div class="title">
    {{ $lang->write('Balance Sheet') }}
    <span class="as-of">{{ $lang->write('As of') }} {{ $asOf }}</span>
</div>

<table class="figures">
    <thead>
        <tr>
            <th style="width: 50%;">{{ $lang->write('Account') }}</th>
            @foreach ($currencies as $c)
                <th style="text-align: right;">{{ $currencyLabel[$c] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr class="section"><td colspan="5">{{ $lang->write('Assets') }}</td></tr>
        @foreach ($assets as $a)
            <tr>
                <td>{{ $lang->write($a['label']) }}</td>
                @foreach ($currencies as $c)
                    <td class="num">{{ _f($balances[$a['key']][$c] ?? 0) }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr class="subtotal">
            <td>{{ $lang->write('Total assets') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($totals['assets'][$c]) }}</td>
            @endforeach
        </tr>

        <tr class="section"><td colspan="5">{{ $lang->write('Liabilities') }}</td></tr>
        @foreach ($liabilities as $l)
            <tr>
                <td>{{ $lang->write($l['label']) }}</td>
                @foreach ($currencies as $c)
                    <td class="num">{{ _f($balances[$l['key']][$c] ?? 0) }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr class="subtotal">
            <td>{{ $lang->write('Total liabilities') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($totals['liabilities'][$c]) }}</td>
            @endforeach
        </tr>

        <tr class="section"><td colspan="5">{{ $lang->write('Equity') }}</td></tr>
        <tr>
            <td>{{ $lang->write('Owner\'s equity') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($ownersEquity[$c]) }}</td>
            @endforeach
        </tr>
        <tr>
            <td>{{ $lang->write('Net income (year to date)') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($netIncome[$c]) }}</td>
            @endforeach
        </tr>
        @foreach ($equityFixed as $e)
            <tr>
                <td>{{ $lang->write($e['label']) }}</td>
                @foreach ($currencies as $c)
                    <td class="num">{{ _f($e['sign'] * (float)($balances[$e['key']][$c] ?? 0)) }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr class="subtotal">
            <td>{{ $lang->write('Total equity') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($totals['equity'][$c]) }}</td>
            @endforeach
        </tr>

        <tr class="equation">
            <td>{{ $lang->write('Total liabilities + equity') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($totals['liabilities'][$c] + $totals['equity'][$c]) }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

<div class="footer">
    {{ $lang->write('Generated') }} {{ date('Y-m-d H:i') }} ·
    {{ $lang->write('Per-currency balances; consolidation requires FX restatement at the reporting rate.') }}
</div>

</body>
</html>
