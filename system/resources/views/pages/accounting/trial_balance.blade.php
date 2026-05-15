@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Trial Balance') }}</h4>
        <small class="text-muted">{{ $lang->write('Balance-sheet rows show current balances; P&L rows show activity within the selected period. Owner\'s equity is the balancing plug.') }}</small>
    </div>
</div>

<form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-auto">
        <label class="form-label small mb-1">{{ $lang->write('Period from') }}</label>
        <input type="date" name="period_from" value="{{ $periodFrom }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">{{ $lang->write('Period to') }}</label>
        <input type="date" name="period_to" value="{{ $periodTo }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit">{{ $lang->write('Refresh') }}</button>
    </div>
</form>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th style="width:80px;">{{ $lang->write('Code') }}</th>
            <th>{{ $lang->write('Account') }}</th>
            <th>{{ $lang->write('Type') }}</th>
            <th class="text-end">USD <span class="text-muted small">DR</span></th>
            <th class="text-end">USD <span class="text-muted small">CR</span></th>
            <th class="text-end">EUR <span class="text-muted small">DR</span></th>
            <th class="text-end">EUR <span class="text-muted small">CR</span></th>
            <th class="text-end">LYD <span class="text-muted small">DR</span></th>
            <th class="text-end">LYD <span class="text-muted small">CR</span></th>
            <th class="text-end">CNY <span class="text-muted small">DR</span></th>
            <th class="text-end">CNY <span class="text-muted small">CR</span></th>
        </tr>
    </thead>
    <tbody>
    @foreach ($rows as $r)
        <tr>
            <td class="text-muted">{{ $r['code'] }}</td>
            <td>{{ $lang->write($r['name']) }}</td>
            <td><span class="badge bg-secondary">{{ $lang->write(ucfirst($r['type'])) }}</span></td>
            @foreach ($currencies as $c)
                @php $v = (float) $r['amounts'][$c]; @endphp
                <td class="text-end">{{ $r['normal_balance'] === 'debit' && abs($v) > 0.0001 ? $data->numberFormat($v) : '' }}</td>
                <td class="text-end">{{ $r['normal_balance'] === 'credit' && abs($v) > 0.0001 ? $data->numberFormat($v) : '' }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
    <tfoot class="table-light">
        <tr style="font-weight:600;">
            <td colspan="3">{{ $lang->write('Totals') }}</td>
            @foreach ($currencies as $c)
                <td class="text-end">{{ $data->numberFormat($totals['debit'][$c]) }}</td>
                <td class="text-end">{{ $data->numberFormat($totals['credit'][$c]) }}</td>
            @endforeach
        </tr>
        <tr class="text-muted">
            <td colspan="3">{{ $lang->write('Net income (period)') }}</td>
            @foreach ($currencies as $c)
                <td class="text-end" colspan="2">{{ $data->numberFormat($netIncome[$c]) }}</td>
            @endforeach
        </tr>
    </tfoot>
</table>
</div>

@endsection
