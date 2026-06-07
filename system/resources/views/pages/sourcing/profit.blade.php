@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{ url('/sourcing/' . $req->id) }}" class="text-muted text-decoration-none">
                <code>{{ $req->request_number }}</code> ›
            </a>
            {{ $lang->write('Profit dashboard') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Sourcing margin + freight margin combined, sliced from the ledger') }}
        </div>
    </div>
</div>

{{-- Grand totals --}}
<div class="row g-3 mb-3">
    @foreach ($currencies as $c)
        @php
            $net  = $grand['net'][$c];
            $cash = $grand['cash_outflow'][$c];
            $impliedMargin = $grand['revenue'][$c] - $cash;
            $hasActivity = abs($grand['revenue'][$c]) > 0.0001 || abs($grand['expense'][$c]) > 0.0001 || abs($cash) > 0.0001;
        @endphp
        @if ($hasActivity)
            <div class="col-12 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">{{ strtoupper($c) }} {{ $lang->write('net') }}</div>
                        <div class="h3 mb-0 {{ $net > 0 ? 'text-success' : ($net < 0 ? 'text-danger' : '') }}">
                            {{ $data->numberFormat($net) }}
                        </div>
                        <div class="small text-muted">
                            {{ $lang->write('Rev') }} {{ $data->numberFormat($grand['revenue'][$c]) }} ·
                            {{ $lang->write('Exp') }} {{ $data->numberFormat($grand['expense'][$c]) }}
                        </div>
                        <hr class="my-2">
                        <div class="small">
                            {{ $lang->write('Cash settled') }}:
                            <strong class="{{ $cash > 0 ? 'text-danger' : ($cash < 0 ? 'text-success' : '') }}">
                                {{ $cash > 0 ? '−' : ($cash < 0 ? '+' : '') }}{{ $data->numberFormat(abs($cash)) }}
                            </strong>
                        </div>
                        <div class="small">
                            {{ $lang->write('Implied cash margin') }}:
                            <strong class="{{ $impliedMargin > 0 ? 'text-success' : ($impliedMargin < 0 ? 'text-danger' : '') }}">
                                {{ $data->numberFormat($impliedMargin) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>

{{-- Sourcing-side card --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                {{ $lang->write('Sourcing side') }}
                <span class="badge bg-secondary">cost_object_type = sourcing_request</span>
            </h5>
            <div class="text-muted small">{{ $req->title }}</div>
        </div>

        @if (count($sourcing['revenue']) + count($sourcing['expense']) + count($sourcing['other']) === 0)
            <p class="text-muted mb-0">{{ $lang->write('No journal activity tagged to this proforma yet. Sourcing revenue posts on Mark Fulfilled.') }}</p>
        @else
            <table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>{{ $lang->write('Code') }}</th>
                    <th>{{ $lang->write('Account') }}</th>
                    <th>{{ $lang->write('Type') }}</th>
                    @foreach ($currencies as $c)<th class="text-end">{{ strtoupper($c) }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ([['revenue', 'success'], ['expense', 'warning'], ['other', 'secondary']] as $pair)
                    @php [$bucket, $badge] = $pair; @endphp
                    @foreach ($sourcing[$bucket] as $r)
                        <tr>
                            <td class="text-muted">{{ $r['code'] }}</td>
                            <td>{{ $lang->write($r['name']) }}</td>
                            <td><span class="badge bg-{{ $badge }}">{{ $bucket === 'other' ? ($r['acct_type'] ?? 'bs') : $bucket }}</span></td>
                            @foreach ($currencies as $c)
                                <td class="text-end">{{ abs($r['amounts'][$c]) > 0.0001 ? $data->numberFormat($r['amounts'][$c]) : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold table-light">
                        <td colspan="3">{{ $lang->write('Sourcing net') }}</td>
                        @foreach ($currencies as $c)
                            @php $sn = $sourcing['totals']['revenue'][$c] - $sourcing['totals']['expense'][$c]; @endphp
                            <td class="text-end {{ $sn > 0 ? 'text-success' : ($sn < 0 ? 'text-danger' : '') }}">
                                {{ $data->numberFormat($sn) }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>

{{-- Freight-side card --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                {{ $lang->write('Freight side') }}
                @if ($req->freight_kind && $req->freight_container_id)
                    <span class="badge bg-secondary">cost_object_type = container_{{ $req->freight_kind }}</span>
                @endif
            </h5>
            @if ($linkedContainer)
                <a href="{{ url('/profits/container/' . $req->freight_kind . '/' . $linkedContainer->id) }}" class="btn btn-sm btn-outline-secondary">
                    {{ $lang->write('Open container view') }}
                </a>
            @endif
        </div>

        @if (!$req->freight_container_id)
            <p class="text-muted mb-0">
                {{ $lang->write('No freight container yet. Use Send to freight on the proforma to create one and the shipping side will start populating here.') }}
            </p>
        @elseif (count($freight['revenue']) + count($freight['expense']) + count($freight['other']) === 0)
            <p class="text-muted mb-0">
                {{ $lang->write('Container created but no journal activity yet — happens when client_withd or supplier_deposit posts for this container.') }}
            </p>
        @else
            <table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>{{ $lang->write('Code') }}</th>
                    <th>{{ $lang->write('Account') }}</th>
                    <th>{{ $lang->write('Type') }}</th>
                    @foreach ($currencies as $c)<th class="text-end">{{ strtoupper($c) }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ([['revenue', 'success'], ['expense', 'warning'], ['other', 'secondary']] as $pair)
                    @php [$bucket, $badge] = $pair; @endphp
                    @foreach ($freight[$bucket] as $r)
                        <tr>
                            <td class="text-muted">{{ $r['code'] }}</td>
                            <td>{{ $lang->write($r['name']) }}</td>
                            <td><span class="badge bg-{{ $badge }}">{{ $bucket === 'other' ? ($r['acct_type'] ?? 'bs') : $bucket }}</span></td>
                            @foreach ($currencies as $c)
                                <td class="text-end">{{ abs($r['amounts'][$c]) > 0.0001 ? $data->numberFormat($r['amounts'][$c]) : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold table-light">
                        <td colspan="3">{{ $lang->write('Freight net') }}</td>
                        @foreach ($currencies as $c)
                            @php $fn = $freight['totals']['revenue'][$c] - $freight['totals']['expense'][$c]; @endphp
                            <td class="text-end {{ $fn > 0 ? 'text-success' : ($fn < 0 ? 'text-danger' : '') }}">
                                {{ $data->numberFormat($fn) }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>

<div class="alert alert-info small">
    {{ $lang->write('Note: supplier / broker payments land in prepayment asset accounts (1200 / 1300) and only become formal expense when explicitly recognised. "Cash settled" above is the realistic cost; "Net" reflects pure P&L accruals.') }}
</div>

@endsection
