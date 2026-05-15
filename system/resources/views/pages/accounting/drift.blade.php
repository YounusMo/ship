@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
    $allZero = $driftCount === 0;
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-md-8 mb-2">
        <h4 class="h4">{{ $lang->write('Drift Detector') }}</h4>
        <small class="text-muted">{{ $lang->write('Side-by-side comparison: journal-derived net balance vs entity-table-derived figure for every account. Drift means a mutation updated one side but not the other — either a wiring gap or an unbackfilled historical row.') }}</small>
    </div>
    <div class="col-md-4 mb-2">
        <form method="get" class="d-flex justify-content-end gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">{{ $lang->write('As of') }}</label>
                <input type="date" name="as_of" value="{{ $asOf }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">{{ $lang->write('Recheck') }}</button>
        </form>
    </div>
</div>

@if ($allZero)
    <div class="alert alert-success">
        <strong>✓ {{ $lang->write('No drift.') }}</strong>
        {{ $lang->write('The journal and the entity tables agree on every account in every currency.') }}
    </div>
@else
    <div class="alert alert-warning">
        <strong>⚠ {{ $lang->write('Drift detected.') }}</strong>
        {{ $lang->write('Accounts with mismatches') }}: <strong>{{ $driftCount }}</strong>.
        <br>
        {{ $lang->write('Net drift per currency') }}:
        @foreach ($currencies as $c)
            @if (abs($driftCurrencies[$c]) > 0.0001)
                <span class="badge bg-light text-dark mx-1">{{ strtoupper($c) }}: {{ $data->numberFormat($driftCurrencies[$c]) }}</span>
            @endif
        @endforeach
    </div>
@endif

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th rowspan="2" style="vertical-align:bottom;">{{ $lang->write('Code') }}</th>
            <th rowspan="2" style="vertical-align:bottom;">{{ $lang->write('Account') }}</th>
            <th rowspan="2" style="vertical-align:bottom;">{{ $lang->write('Type') }}</th>
            <th colspan="3" class="text-center" style="background:#eef2f8;">USD</th>
            <th colspan="3" class="text-center" style="background:#eef2f8;">EUR</th>
            <th colspan="3" class="text-center" style="background:#eef2f8;">LYD</th>
            <th colspan="3" class="text-center" style="background:#eef2f8;">CNY</th>
        </tr>
        <tr>
            @foreach ($currencies as $c)
                <th class="text-end small text-muted">{{ $lang->write('Journal') }}</th>
                <th class="text-end small text-muted">{{ $lang->write('Entity') }}</th>
                <th class="text-end small text-muted">{{ $lang->write('Drift') }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
    @foreach ($rows as $r)
        <tr class="{{ $r['has_drift'] ? 'table-warning' : '' }}">
            <td class="text-muted small">{{ $r['code'] }}</td>
            <td>
                {{ $r['name'] }}
                @if (!$r['has_key'])
                    <span class="badge bg-light text-muted" title="{{ $lang->write('No derivation key — not auto-derived from entity tables') }}">{{ $lang->write('manual') }}</span>
                @endif
            </td>
            <td><small class="text-muted">{{ ucfirst($r['type']) }} ({{ $r['normal'] }})</small></td>
            @foreach ($currencies as $c)
                @php $d = $r['drift'][$c]; @endphp
                <td class="text-end {{ abs($r['journal'][$c]) < 0.0001 ? 'text-muted' : '' }}">
                    {{ abs($r['journal'][$c]) > 0.0001 ? $data->numberFormat($r['journal'][$c]) : '' }}
                </td>
                <td class="text-end {{ abs($r['entity'][$c]) < 0.0001 ? 'text-muted' : '' }}">
                    {{ abs($r['entity'][$c]) > 0.0001 ? $data->numberFormat($r['entity'][$c]) : '' }}
                </td>
                <td class="text-end {{ abs($d) > 0.0001 ? ($d > 0 ? 'text-success fw-bold' : 'text-danger fw-bold') : 'text-muted' }}">
                    {{ abs($d) > 0.0001 ? ($d > 0 ? '+' : '') . $data->numberFormat($d) : '✓' }}
                </td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
</div>

<div class="mt-3 small text-muted">
    <p class="mb-1"><strong>{{ $lang->write('Reading the table') }}:</strong></p>
    <ul class="mb-2">
        <li>{{ $lang->write('Journal column: SUM(DR) − SUM(CR) on this account_code in the journal (open entries only).') }}</li>
        <li>{{ $lang->write('Entity column: the figure the original trial balance derives from balances on clients/suppliers/brokers/branches plus today\'s P&L rows.') }}</li>
        <li>{{ $lang->write('Drift: journal − entity. Green = journal records more activity than entity; red = entity has activity the journal missed.') }}</li>
        <li>{{ $lang->write('"manual" accounts have no derivation_key — they need explicit posting and don\'t appear in the entity-derived trial balance, so their entity column is always 0.') }}</li>
    </ul>
</div>

@endsection
