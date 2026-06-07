@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
    $kindLabel = $kind === 'sky' ? $lang->write('Air freight') : $lang->write('Sea freight');
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{ url('/sourcing/' . $req->id) }}" class="text-muted text-decoration-none">
                <code>{{ $req->request_number }}</code> ›
            </a>
            {{ $lang->write('Send to') }} {{ $kindLabel }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('The proforma supplies the client and items. Fill in the carrier and freight details below — we won\'t ask you to re-enter what we already know.') }}
        </div>
    </div>
</div>

<input type="hidden" id="sourcing_id" value="{{ $req->id }}">
<input type="hidden" id="handoff_kind" value="{{ $kind }}">

<div class="row g-3">
    {{-- Left column: form --}}
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">{{ $lang->write('Carrier + freight') }}</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ $lang->write('AWB / B/L number') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="awb_number" maxlength="191" placeholder="{{ $lang->write('e.g. AWB-176-1234-5678') }}">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ $lang->write('Container / Trip name') }}</label>
                        <input type="text" class="form-control" id="container_name" maxlength="191" value="{{ $req->request_number }}" placeholder="{{ $lang->write('Defaults to the proforma number') }}">
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="form-label">{{ $lang->write('Arrival date') }} <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="arrival">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">{{ $lang->write('Size') }}</label>
                        <input type="text" class="form-control" id="size" maxlength="32" placeholder="{{ $kind === 'sea' ? '20GP / 40HQ / LCL' : 'box / pallet' }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">{{ $lang->write('Ship from') }}</label>
                        <input type="text" class="form-control" id="ship_from" maxlength="191" placeholder="{{ $lang->write('Origin city / port') }}">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ $lang->write('Carrier (shipping line)') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="supplier">
                            <option value="">{{ $lang->write('Select') }}</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @if (count($suppliers) < 1)
                            <small class="text-warning">{{ $lang->write('No carriers configured for this shipping mode — add one from Shipping Lines first.') }}</small>
                        @endif
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ $lang->write('Branch') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="branch">
                            <option value="">{{ $lang->write('Select') }}</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}">{{ $lang->branch($b->id) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ $lang->write('Pay carrier') }}</label>
                        <select class="form-select" id="payment_supplier">
                            <option value="pay1">{{ $lang->write('From carrier account (later)') }}</option>
                            <option value="pay2">{{ $lang->write('Cash now') }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ $lang->write('Charge client') }}</label>
                        <select class="form-select" id="payment">
                            <option value="pay1">{{ $lang->write('Account deduction') }}</option>
                            <option value="pay2">{{ $lang->write('Cash payment') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">{{ $lang->write('Freight cost (USD)') }} <span class="text-danger">*</span></label>
                        <input type="number" step="any" class="form-control" id="cost" placeholder="0">
                        <small class="text-muted">{{ $lang->write('What the carrier charges us') }}</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">{{ $lang->write('Client freight price (USD)') }} <span class="text-danger">*</span></label>
                        <input type="number" step="any" class="form-control" id="client_price" placeholder="0">
                        <small class="text-muted">{{ $lang->write('What we charge the client for shipping') }}</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">{{ $lang->write('Commission (USD)') }}</label>
                        <input type="number" step="any" class="form-control" id="commission" value="0" placeholder="0">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ $lang->write('Packaging') }}</label>
                        <input type="text" class="form-control" id="packaging_type" maxlength="64" placeholder="{{ $lang->write('e.g. carton, pallet, bag') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ $lang->write('Notes') }}</label>
                        <textarea class="form-control" id="notes" rows="2" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a class="btn btn-secondary" href="{{ url('/sourcing/' . $req->id) }}">{{ $lang->write('Cancel') }}</a>
                    <button class="btn btn-success" onclick="submitHandoff()">{{ $lang->write('Create container') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Right column: proforma summary --}}
    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="text-muted small mb-2">{{ $lang->write('From proforma') }}</h6>
                <div class="h5 mb-1"><code>{{ $req->request_number }}</code></div>
                <div class="text-muted">{{ $req->title }}</div>
                <hr>
                <div class="d-flex justify-content-between"><span class="text-muted">{{ $lang->write('Client') }}</span><strong>{{ $client->name ?? '' }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">{{ $lang->write('Items') }}</span><strong>{{ count($items) }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">{{ $lang->write('Total pieces') }}</span><strong>{{ rtrim(rtrim(number_format($totals['pieces'], 4, '.', ''), '0'), '.') }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">{{ $lang->write('Total weight (kg)') }}</span><strong>{{ number_format($totals['weight_kg'], 2) }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">{{ $lang->write('Total CBM') }}</span><strong>{{ number_format($totals['cbm'], 3) }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">{{ $lang->write('Goods cost (USD)') }}</span><strong>{{ number_format($totals['cost_usd'], 2) }}</strong></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="text-muted small mb-2">{{ $lang->write('Items being shipped') }}</h6>
                <ul class="list-group list-group-flush">
                @foreach ($items as $it)
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <div>
                            <div class="fw-semibold">{{ $it->name }}</div>
                            <div class="small text-muted">
                                {{ rtrim(rtrim(number_format((float) $it->quantity, 4, '.', ''), '0'), '.') }} {{ $it->unit }}
                                @if ($it->weight_kg) · {{ number_format((float) $it->weight_kg, 2) }} kg @endif
                            </div>
                        </div>
                    </li>
                @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function submitHandoff() {
    const payload = {
        kind: document.getElementById('handoff_kind').value,
        awb_number:      document.getElementById('awb_number').value,
        container_name:  document.getElementById('container_name').value,
        arrival:         document.getElementById('arrival').value,
        size:            document.getElementById('size').value,
        ship_from:       document.getElementById('ship_from').value,
        supplier:        document.getElementById('supplier').value,
        branch:          document.getElementById('branch').value,
        payment_supplier:document.getElementById('payment_supplier').value,
        payment:         document.getElementById('payment').value,
        cost:            document.getElementById('cost').value,
        client_price:    document.getElementById('client_price').value,
        commission:      document.getElementById('commission').value || 0,
        packaging_type:  document.getElementById('packaging_type').value,
        notes:           document.getElementById('notes').value,
    };
    const required = ['awb_number','arrival','supplier','branch','cost','client_price'];
    for (const k of required) {
        if (!payload[k]) { alert('Please fill: ' + k); return; }
    }
    const fd = new FormData();
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    Object.entries(payload).forEach(([k,v]) => fd.append(k, v));

    fetch('/sourcing/{{ $req->id }}/handoff', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.type === 'success') {
                alert('Container created. Redirecting to ' + res.redirect + '.');
                window.location.href = res.redirect;
            } else {
                alert(res.message || 'Failed');
            }
        })
        .catch(e => alert('Network error: ' + e.message));
}
</script>

@endsection
