@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('AR Aging') }}</h4>
        <small class="text-muted">{{ $lang->write('Outstanding client balances bucketed by days since last activity. Negative numbers mean the client owes the company.') }}</small>
    </div>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>{{ $lang->write('Client') }}</th>
            <th>{{ $lang->write('Last activity') }}</th>
            <th>{{ $lang->write('Days') }}</th>
            <th>{{ $lang->write('Bucket') }}</th>
            <th class="text-end">USD</th>
            <th class="text-end">EUR</th>
            <th class="text-end">LYD</th>
            <th class="text-end">CNY</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($aging as $r)
        <tr>
            <td>
                <a href="{{ url('/clients/reports/statement/'.$r['id']) }}" target="_blank">
                    {{ $r['code'] }} — {{ $r['name'] }}
                </a>
            </td>
            <td>{{ $r['last_date'] ?? '—' }}</td>
            <td>{{ $r['days'] >= 9999 ? '—' : $r['days'] }}</td>
            <td>
                @php
                    $cls = match ($r['bucket']) {
                        'current' => 'bg-success',
                        'b31_60'  => 'bg-info',
                        'b61_90'  => 'bg-warning',
                        'b91_180' => 'bg-orange',
                        default   => 'bg-danger',
                    };
                @endphp
                <span class="badge {{ $cls }}">{{ $buckets[$r['bucket']] }}</span>
            </td>
            @foreach ($currencies as $c)
                @php $v = (float) $r['balances'][$c]; @endphp
                <td class="text-end {{ $v < 0 ? 'text-danger' : ($v > 0 ? '' : 'text-muted') }}">
                    {{ abs($v) > 0.0001 ? $data->numberFormat($v) : '' }}
                </td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
    <tfoot class="table-light">
        @foreach ($buckets as $bk => $label)
            <tr>
                <td colspan="4"><strong>{{ $label }}</strong></td>
                @foreach ($currencies as $c)
                    <td class="text-end">{{ $data->numberFormat($bucketTotals[$c][$bk]) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tfoot>
</table>
</div>

@endsection
