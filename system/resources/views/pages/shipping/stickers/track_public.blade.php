@php
    /**
     * Public shipment tracking page. No layout extends — anyone with the
     * URL can see this, so we don't expose auth UI or sidebar.
     *
     * Field allow-list is enforced by the controller. The view trusts $p
     * and ONLY shows what's in $p.
     */
    $brand   = '#0e2a47';
    $accent  = '#c9a246';
    $muted   = '#5b667a';

    $statusColors = [
        'received'      => ['#1e6b3a', '#e6f4ea'],
        'in_container'  => ['#0b6bb0', '#e3f0fb'],
        'in_transit'    => ['#0b6bb0', '#e3f0fb'],
        'delivered'     => ['#1e6b3a', '#e6f4ea'],
        'cancelled'     => ['#b3261e', '#fbe9e7'],
        'unknown'       => [$muted,   '#f3f5f9'],
    ];
    [$statusFg, $statusBg] = $statusColors[$p['status_key']] ?? $statusColors['unknown'];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Track {{ $p['tracking_code'] }}</title>
    <style>
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f4f6fa;
            color: #1a2233;
            line-height: 1.45;
        }
        .container { max-width: 460px; margin: 0 auto; padding: 16px; }

        .header {
            background: {{ $brand }};
            color: #fff;
            border-radius: 14px;
            padding: 18px 18px 22px;
            text-align: center;
            box-shadow: 0 6px 18px rgba(14,42,71,.18);
        }
        .header .tracking {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 4px;
            word-break: break-all;
        }
        .header .mode {
            display: inline-block;
            background: {{ $accent }};
            color: {{ $brand }};
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .status-card {
            margin-top: 14px;
            background: #fff;
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(14,42,71,.06);
        }
        .status-pill {
            display: inline-block;
            background: {{ $statusBg }};
            color: {{ $statusFg }};
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 14px;
        }
        .status-card .piece {
            color: {{ $muted }};
            font-size: 13px;
            margin-top: 8px;
        }
        .status-card .last-update {
            color: {{ $muted }};
            font-size: 12px;
            margin-top: 4px;
        }

        .grid {
            margin-top: 14px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(14,42,71,.06);
            overflow: hidden;
        }
        .grid .item {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            border-top: 1px solid #eef0f5;
            font-size: 14px;
        }
        .grid .item:first-child { border-top: 0; }
        .grid .lbl { color: {{ $muted }}; }
        .grid .val { font-weight: 600; text-align: right; }

        .footer {
            text-align: center;
            color: {{ $muted }};
            font-size: 11px;
            margin-top: 18px;
            padding-bottom: 18px;
            line-height: 1.5;
        }

        .cancelled-banner {
            background: #fbe9e7;
            color: #b3261e;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            margin-top: 14px;
            text-align: center;
            font-weight: 600;
        }

        @media (prefers-color-scheme: dark) {
            body { background: #0f1622; color: #e6e9f0; }
            .status-card, .grid { background: #1a2233; box-shadow: 0 2px 6px rgba(0,0,0,.4); }
            .grid .item { border-color: #283248; }
            .grid .lbl { color: #8d97ad; }
            .footer { color: #8d97ad; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @include('partials.brand_mark_html', ['settings' => $settings, 'brandColor' => $brand, 'accentColor' => $accent, 'size' => 38])
            <div style="margin-top: 8px; font-size: 13px; font-weight: 600; opacity: .9;">{{ $settings['company_name'] ?? '' }}</div>
            <div class="mode">{{ $p['mode'] === 'air' ? 'Air shipment' : 'Sea shipment' }}</div>
            <div class="tracking">{{ $p['tracking_code'] }}</div>
        </div>

        @if (!$p['piece_active'])
            <div class="cancelled-banner">
                This sticker has been cancelled. Please contact the warehouse for a new tracking code.
            </div>
        @endif

        <div class="status-card">
            <div class="status-pill">{{ $p['status'] }}</div>
            <div class="piece">Piece {{ $p['piece_index'] }} of {{ $p['piece_total'] }}</div>
            @if (!empty($p['last_update']))
                <div class="last-update">Last update: {{ $p['last_update'] }}</div>
            @endif
        </div>

        <div class="grid">
            @if (!empty($p['client_code']))
                <div class="item"><div class="lbl">Recipient code</div><div class="val">{{ $p['client_code'] }}</div></div>
            @endif
            @if (!empty($p['company']))
                <div class="item"><div class="lbl">Shipper</div><div class="val">{{ $p['company'] }}</div></div>
            @endif
            @if (!empty($p['ship_from']))
                <div class="item"><div class="lbl">Origin</div><div class="val">{{ ucfirst($p['ship_from']) }}</div></div>
            @endif
            @if (!empty($p['category']))
                <div class="item"><div class="lbl">Category</div><div class="val">{{ $p['category'] }}</div></div>
            @endif
            @if (!empty($p['brand']))
                <div class="item"><div class="lbl">Brand</div><div class="val">{{ $p['brand'] }}</div></div>
            @endif
            @if (!empty($p['type']))
                <div class="item"><div class="lbl">Package type</div><div class="val">{{ ucfirst($p['type']) }}</div></div>
            @endif
            @if ($p['kg'] !== null && $p['kg'] !== '')
                <div class="item"><div class="lbl">Weight</div><div class="val">{{ $p['kg'] }} KG</div></div>
            @endif
            @if ($p['cbm'] !== null && $p['cbm'] !== '')
                <div class="item"><div class="lbl">Volume</div><div class="val">{{ $p['cbm'] }} CBM</div></div>
            @endif
            @if (!empty($p['received_date']))
                <div class="item"><div class="lbl">Received at warehouse</div><div class="val">{{ $p['received_date'] }}</div></div>
            @endif
        </div>

        <div class="footer">
            This page only shows shipment-tracking information.<br>
            For pricing, balances or any account details, please log in to the operator portal.
        </div>
    </div>
</body>
</html>
