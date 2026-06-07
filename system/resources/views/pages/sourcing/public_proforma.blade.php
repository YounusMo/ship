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
    $isAccepted = $req->status === 'accepted' || $req->status === 'fulfilled';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Proforma {{ $req->request_number }} — {{ $settings['company_name'] ?? '' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f5f7fb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #1f2937; }
        .doc-wrap { max-width: 920px; margin: 24px auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(15,23,42,.06); overflow: hidden; }
        .doc-head { padding: 28px 36px; background: linear-gradient(135deg, #1e3a8a, #0f172a); color: #fff; }
        .doc-head .title { font-size: 24px; font-weight: 700; letter-spacing: 1px; }
        .doc-head .meta { color: rgba(255,255,255,.75); font-size: 13px; }
        .doc-body { padding: 28px 36px; }
        .panel { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 18px; }
        .muted { color: #6b7280; font-size: 13px; }
        .thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
        .totals { font-size: 16px; }
        .grand { color: #047857; font-weight: 700; font-size: 22px; }
        .terms { background: #f9fafb; border-left: 3px solid #94a3b8; padding: 12px 14px; color: #4b5563; white-space: pre-wrap; font-size: 14px; }
        .sticky-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e5e7eb; padding: 14px 36px; margin: 0 -36px -28px; }
        .badge-status { background: #dbeafe; color: #1e40af; }
        .badge-accepted { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>

<div class="doc-wrap">
    <div class="doc-head d-flex justify-content-between align-items-center">
        <div>
            <div class="title">PROFORMA INVOICE</div>
            <div class="meta">{{ $settings['company_name'] ?? '' }} · No. <strong>{{ $req->request_number }}</strong></div>
        </div>
        <div class="text-end">
            @if ($isAccepted)
                <span class="badge badge-accepted px-3 py-2">{{ ucfirst($req->status) }}</span>
            @else
                <span class="badge badge-status px-3 py-2">Awaiting your approval</span>
            @endif
            <div class="meta mt-2">{{ $req->sent_at ? 'Sent ' . substr($req->sent_at, 0, 10) : '' }}</div>
        </div>
    </div>

    <div class="doc-body">

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="panel">
                    <div class="muted">BILL TO</div>
                    <div style="font-weight:600;font-size:16px;">{{ $client->name ?? '' }}</div>
                    <div class="muted">
                        @if (!empty($client->code)) Code: {{ $client->code }}<br>@endif
                        @if (!empty($client->phone)) {{ $client->phone }}<br>@endif
                        @if (!empty($client->email)) {{ $client->email }} @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel">
                    <div class="muted">SUBJECT</div>
                    <div style="font-weight:600;font-size:16px;">{{ $req->title }}</div>
                    @if ($req->description)
                        <div class="muted mt-1">{{ $req->description }}</div>
                    @endif
                </div>
            </div>
        </div>

        <h5 class="mb-2">Items</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:80px;"></th>
                        <th>Product</th>
                        <th class="text-end" style="width:90px;">Qty</th>
                        <th class="text-end" style="width:140px;">Unit price</th>
                        <th class="text-end" style="width:140px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($items as $it)
                    @php
                        $primary = ($photos[$it->id] ?? null) ? $photos[$it->id][0] : null;
                        $lineDisp = $toDisplay((float) $it->quantity * (float) $it->unit_price_to_client, $it->unit_cost_currency);
                        $unitDisp = $toDisplay((float) $it->unit_price_to_client, $it->unit_cost_currency);
                    @endphp
                    <tr>
                        <td>
                            @if ($primary)
                                <a href="{{ asset('storage/' . $primary->path) }}" target="_blank">
                                    <img class="thumb" src="{{ asset('storage/' . $primary->path) }}">
                                </a>
                            @else
                                <div class="thumb" style="background:#f3f4f6;"></div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight:600;">{{ $it->name }}</div>
                            @if ($it->code) <div class="muted">SKU {{ $it->code }}</div> @endif
                            @if ($it->description) <div class="muted">{{ $it->description }}</div> @endif
                        </td>
                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $it->quantity, 4, '.', ''), '0'), '.') }} <span class="muted">{{ $it->unit }}</span></td>
                        <td class="text-end">{{ number_format($unitDisp, 2) }} {{ $displayCcy }}</td>
                        <td class="text-end fw-semibold">{{ number_format($lineDisp, 2) }} {{ $displayCcy }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="row mt-4">
            <div class="col-md-7">
                @if (count($payments) > 0)
                    <h5 class="mb-2">Payment plan</h5>
                    <table class="table table-sm">
                        <thead><tr><th>#</th><th>Installment</th><th class="text-end">%</th><th class="text-end">Amount</th><th>Due</th></tr></thead>
                        <tbody>
                        @foreach ($payments as $p)
                            <tr>
                                <td>{{ $p->sequence }}</td>
                                <td>{{ $p->label }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $p->percentage, 4, '.', ''), '0'), '.') }}</td>
                                <td class="text-end">{{ number_format((float) $p->amount, 2) }} {{ strtoupper($p->currency) }}</td>
                                <td>{{ $p->due_date ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
                @if (!empty($req->terms_text))
                    <h5 class="mt-3 mb-2">Terms</h5>
                    <div class="terms">{{ $req->terms_text }}</div>
                @endif
            </div>
            <div class="col-md-5">
                <div class="panel totals">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="muted">Items subtotal</span>
                        <span>{{ number_format((float) $req->items_subtotal, 2) }} {{ $displayCcy }}</span>
                    </div>
                    @if ($req->commission_mode === 'visible_separate' && (float) $req->commission_amount > 0)
                        @php $commDisp = $toDisplay((float) $req->commission_amount, $req->commission_currency ?: $displayCcy); @endphp
                        <div class="d-flex justify-content-between mb-2">
                            <span class="muted">Service commission</span>
                            <span>{{ number_format($commDisp, 2) }} {{ $displayCcy }}</span>
                        </div>
                    @endif
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="muted">TOTAL</span>
                        <span class="grand">{{ number_format((float) $req->proforma_total, 2) }} {{ $displayCcy }}</span>
                    </div>
                </div>
                <div class="text-muted small mt-3">
                    {{ $settings['receipt_footer'] ?? '' }}
                </div>
            </div>
        </div>

        @php
            $clientDocs = collect($documents ?? [])->where('visibility', 'client_visible')->values();
        @endphp
        @if ($clientDocs->count() > 0)
            <div class="panel mt-4">
                <div class="muted small mb-2">ATTACHED DOCUMENTS</div>
                <ul class="list-unstyled mb-0">
                    @foreach ($clientDocs as $d)
                        <li class="mb-1">
                            📄
                            <a href="{{ asset('storage/' . $d->path) }}" target="_blank" class="text-decoration-none">
                                {{ $d->label ?: $d->original_name ?: basename($d->path) }}
                            </a>
                            <span class="muted small">
                                @if ($d->size_bytes) ({{ number_format($d->size_bytes / 1024, 1) }} KB) @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!empty($timeline))
            <div class="panel mt-4">
                <div class="muted small mb-2">ACTIVITY</div>
                <ul class="list-unstyled mb-0" style="font-size:14px;">
                    @foreach ($timeline as $ev)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $ev['label'] }}</span>
                            <span class="muted small">{{ substr($ev['at'], 0, 19) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="sticky-bar d-flex justify-content-between align-items-center mt-4">
            <div>
                <a href="{{ url('/proforma/' . $token . '/pdf') }}" target="_blank" class="btn btn-outline-secondary">
                    Download PDF
                </a>
            </div>
            <div>
                @if (!$isAccepted)
                    <button class="btn btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#changeRequestModal">
                        Request changes
                    </button>
                    <button class="btn btn-success btn-lg" onclick="approveProforma()">I approve this proforma</button>
                @else
                    <span class="badge badge-accepted px-3 py-2">Approved</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Change request modal --}}
@if (!$isAccepted)
    <div class="modal fade" id="changeRequestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request changes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Tell us what you'd like to change. Our team will update the proforma and send you a fresh version.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Your message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="cr_comment" rows="3" maxlength="5000" placeholder="Free-form notes for our team."></textarea>
                    </div>

                    @if (count($items) > 0)
                        <div class="mb-3">
                            <label class="form-label">Specific item changes <span class="text-muted small">(optional)</span></label>
                            <div class="muted small mb-2">
                                Leave a row blank if you do not want to change that item.
                            </div>
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Current qty</th>
                                        <th class="text-end" style="width:110px;">New qty</th>
                                        <th class="text-end">Current unit</th>
                                        <th class="text-end" style="width:110px;">New unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($items as $it)
                                    <tr>
                                        <td>
                                            <div style="font-weight:600;">{{ $it->name }}</div>
                                            @if ($it->code)<div class="muted small">{{ $it->code }}</div>@endif
                                        </td>
                                        <td class="text-end">
                                            {{ rtrim(rtrim(number_format((float) $it->quantity, 4, '.', ''), '0'), '.') }} {{ $it->unit }}
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0" class="form-control form-control-sm cr-item-qty" data-item-id="{{ $it->id }}" placeholder="—">
                                        </td>
                                        <td class="text-end">
                                            {{ number_format((float) $it->unit_price_to_client, 2) }}
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0" class="form-control form-control-sm cr-item-price" data-item-id="{{ $it->id }}" placeholder="—">
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Reply to <span class="text-muted small">(optional)</span></label>
                        <input type="email" class="form-control" id="cr_reply_email" maxlength="191" placeholder="If different from the email we have on file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitChangeRequest()">Send request</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitChangeRequest() {
            const comment = document.getElementById('cr_comment').value.trim();
            const reply   = document.getElementById('cr_reply_email').value.trim();

            // Collect any structured per-item suggestions the client filled.
            const changes = [];
            document.querySelectorAll('.cr-item-qty, .cr-item-price').forEach(el => {
                if (!el.value || el.value === '') return;
                const id = el.getAttribute('data-item-id');
                let row = changes.find(c => c.item_id == id);
                if (!row) { row = { item_id: parseInt(id, 10) }; changes.push(row); }
                if (el.classList.contains('cr-item-qty')) row.qty = parseFloat(el.value);
                if (el.classList.contains('cr-item-price')) row.unit_price_to_client = parseFloat(el.value);
            });

            if (!comment && changes.length === 0) {
                alert('Please tell us what you would like to change, or fill in at least one item change.');
                return;
            }

            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            // The server requires a comment; if the client only filled item
            // changes, send a stock note so the message field is satisfied.
            fd.append('comment', comment || 'See item changes below.');
            if (reply) fd.append('reply_to_email', reply);
            changes.forEach((c, i) => {
                fd.append(`suggested_changes[${i}][item_id]`, c.item_id);
                if (c.qty !== undefined) fd.append(`suggested_changes[${i}][qty]`, c.qty);
                if (c.unit_price_to_client !== undefined) fd.append(`suggested_changes[${i}][unit_price_to_client]`, c.unit_price_to_client);
            });

            fetch('{{ url('/proforma/' . $token . '/request-changes') }}', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.type === 'success') {
                        alert('Thank you. Your request has been sent — we will get back to you shortly.');
                        bootstrap.Modal.getInstance(document.getElementById('changeRequestModal')).hide();
                    } else {
                        alert(res.message || 'Could not send. Please try again or contact us directly.');
                    }
                })
                .catch(() => alert('Network error. Please try again.'));
        }
    </script>
@endif

<script>
function approveProforma() {
    if (!confirm('Confirm: approve this proforma? This is binding once submitted.')) return;
    fetch('{{ url('/proforma/' . $token . '/approve') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(res => {
        if (res.type === 'success') {
            alert('Thank you — your approval has been recorded.');
            window.location.reload();
        } else {
            alert(res.message || 'Could not approve. Please contact the company.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

</body>
</html>
