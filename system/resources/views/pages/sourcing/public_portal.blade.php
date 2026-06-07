@php
    $statusBadge = [
        'open'      => 'bg-secondary',
        'searching' => 'bg-info',
        'quoted'    => 'bg-primary',
        'accepted'  => 'bg-warning',
        'fulfilled' => 'bg-success',
        'canceled'  => 'bg-dark',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $client->name ?? 'Client' }} — Proformas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f5f7fb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1f2937; }
        .wrap { max-width: 980px; margin: 24px auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(15,23,42,.06); overflow: hidden; }
        .head { padding: 28px 36px; background: linear-gradient(135deg, #1e3a8a, #0f172a); color: #fff; }
        .head .title { font-size: 22px; font-weight: 700; }
        .head .meta { color: rgba(255,255,255,.75); }
        .body { padding: 28px 36px; }
        .muted { color: #6b7280; font-size: 13px; }
        table thead { background: #f3f4f6; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; color: #fff; }
    </style>
</head>
<body>

<div class="wrap">
    <div class="head">
        <div class="title">{{ $settings['company_name'] ?? 'Company' }}</div>
        <div class="meta">Proformas for <strong>{{ $client->name ?? '' }}</strong></div>
    </div>

    <div class="body">
        @if (count($rows) < 1)
            <p class="muted mb-0">No proformas have been issued yet. We will list them here as they are created.</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                            <th>Sent</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td><code>{{ $r->request_number }}</code></td>
                            <td>{{ $r->title }}</td>
                            <td>
                                <span class="pill" style="background: var(--bs-{{ str_replace('bg-', '', $statusBadge[$r->status] ?? 'bg-secondary') }});">
                                    {{ ucfirst($r->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                {{ number_format((float) $r->proforma_total, 2) }}
                                <span class="muted">{{ strtoupper($r->display_currency ?: $r->currency ?: 'usd') }}</span>
                            </td>
                            <td class="muted small">{{ $r->sent_at ? substr($r->sent_at, 0, 10) : '—' }}</td>
                            <td class="text-end">
                                @if ($r->share_token && (!$r->share_token_expires_at || strtotime($r->share_token_expires_at) > time()))
                                    <a class="btn btn-sm btn-outline-primary" href="{{ url('/proforma/' . $r->share_token) }}" target="_blank">View</a>
                                @else
                                    <span class="muted small">link unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="muted small mt-2">
                {{ $settings['receipt_footer'] ?? '' }}
            </div>
        @endif
    </div>
</div>

</body>
</html>
