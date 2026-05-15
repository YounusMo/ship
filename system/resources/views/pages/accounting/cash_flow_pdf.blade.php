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
    .title .period { float: right; background: {{ $accentColor }}; color: {{ $brandColor }};
        padding: 2px 8px; border-radius: 3px; font-size: 11px; }

    table.figures { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.figures th, table.figures td { border: 1px solid {{ $border }}; padding: 6px 8px; }
    table.figures th { background: #fafbfc; color: {{ $muted }}; font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; }
    table.figures td.num { text-align: right; font-variant-numeric: tabular-nums; }
    table.figures tr.section td { background: {{ $brandColor }}; color: white; font-weight: 700; text-transform: uppercase; font-size: 10px; }
    table.figures tr.subtotal td { background: #f3f5f9; font-weight: 700; }
    table.figures tr.net td { background: {{ $accentColor }}; color: {{ $brandColor }}; font-weight: 700; }
    table.figures td.outflow { color: #b3261e; }
    table.figures td.inflow  { color: #1e6b3a; }

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
    {{ $lang->write('Cash Flow Statement') }}
    <span class="period">{{ $from }} → {{ $to }}</span>
</div>

<table class="figures">
    <thead>
        <tr>
            <th style="width: 50%;">{{ $lang->write('Item') }}</th>
            @foreach ($currencies as $c)
                <th style="text-align: right;">{{ $currencyLabel[$c] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr class="subtotal">
            <td>{{ $lang->write('Beginning cash on hand') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($beginningCash[$c]) }}</td>
            @endforeach
        </tr>

        <tr class="section"><td colspan="5">{{ $lang->write('Cash inflows') }}</td></tr>
        @foreach ($categories as $key => $cat)
            @if ($cat['sign'] === '+')
                <tr>
                    <td>{{ $lang->write($cat['label']) }}</td>
                    @foreach ($currencies as $c)
                        <td class="num inflow">{{ _f($sums[$key][$c] ?? 0) }}</td>
                    @endforeach
                </tr>
            @endif
        @endforeach

        <tr class="section"><td colspan="5">{{ $lang->write('Cash outflows') }}</td></tr>
        @foreach ($categories as $key => $cat)
            @if ($cat['sign'] === '-')
                <tr>
                    <td>{{ $lang->write($cat['label']) }}</td>
                    @foreach ($currencies as $c)
                        <td class="num outflow">({{ _f($sums[$key][$c] ?? 0) }})</td>
                    @endforeach
                </tr>
            @endif
        @endforeach

        <tr class="subtotal">
            <td>{{ $lang->write('Net change in cash') }}</td>
            @foreach ($currencies as $c)
                <td class="num {{ $netChange[$c] >= 0 ? 'inflow' : 'outflow' }}">
                    {{ $netChange[$c] >= 0 ? '+' : '' }}{{ _f($netChange[$c]) }}
                </td>
            @endforeach
        </tr>

        <tr class="net">
            <td>{{ $lang->write('Ending cash on hand') }}</td>
            @foreach ($currencies as $c)
                <td class="num">{{ _f($endingCash[$c]) }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

<div class="footer">
    {{ $lang->write('Generated') }} {{ date('Y-m-d H:i') }} ·
    {{ $lang->write('Direct method — figures derived from the cash ledger (branches_transactions). Per-currency view; no FX consolidation.') }}
</div>

</body>
</html>
