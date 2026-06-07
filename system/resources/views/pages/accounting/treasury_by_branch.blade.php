@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $lang->write('Treasury by branch') }}</h1>
        <div class="page-subtitle">
            {{ $lang->write('Live cash on hand per branch per currency, with the most recent cash-count drift.') }}
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="text-muted small">{{ $lang->write('Total system cash (USD eq.)') }}</div>
                <div class="h3 mb-0">{{ $data->numberFormat($totals_usd) }} <span class="text-muted">USD</span></div>
            </div>
            @foreach ($currencies as $c)
                <div class="col-6 col-md-2">
                    <div class="text-muted small">{{ strtoupper($c) }}</div>
                    <div class="h5 mb-0">{{ $data->numberFormat($totals[$c]) }}</div>
                    @if ($c !== 'usd' && !empty($rates[$c]))
                        <div class="small text-muted">@ {{ $rates[$c] }}/USD</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ $lang->write('Branch') }}</th>
                        @foreach ($currencies as $c)
                            <th class="text-end">{{ strtoupper($c) }}</th>
                        @endforeach
                        <th class="text-end">{{ $lang->write('USD eq.') }}</th>
                        <th>{{ $lang->write('Last count') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>
                                <strong>{{ $lang->branch($r['branch']->id) }}</strong>
                                <div class="small text-muted">#{{ $r['branch']->id }}</div>
                            </td>
                            @foreach ($currencies as $c)
                                @php
                                    $bal = $r['balances'][$c];
                                    $ci  = $r['countInfo'][$c] ?? null;
                                    $rowClass = $bal < 0 ? 'text-danger' : '';
                                @endphp
                                <td class="text-end {{ $rowClass }}">
                                    {{ $data->numberFormat($bal) }}
                                    @if ($ci && abs($ci['variance']) > 0.0001)
                                        <div class="small {{ $ci['variance'] > 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $lang->write('Drift') }}:
                                            {{ $ci['variance'] > 0 ? '+' : '' }}{{ $data->numberFormat($ci['variance']) }}
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-end fw-semibold">
                                {{ $data->numberFormat($r['branch_usd']) }}
                            </td>
                            <td class="small text-muted">
                                @php
                                    $dates = array_filter(array_map(fn ($x) => $x['count_date'] ?? null, $r['countInfo']));
                                    $most  = $dates ? max($dates) : null;
                                @endphp
                                {{ $most ?? '—' }}
                                @if ($most)
                                    <div>
                                        <a href="{{ url('/accounting/cash-counts') }}" class="small">
                                            {{ $lang->write('History') }} →
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-secondary fw-semibold">
                        <td>{{ $lang->write('All branches') }}</td>
                        @foreach ($currencies as $c)
                            <td class="text-end">{{ $data->numberFormat($totals[$c]) }}</td>
                        @endforeach
                        <td class="text-end">{{ $data->numberFormat($totals_usd) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@endsection
