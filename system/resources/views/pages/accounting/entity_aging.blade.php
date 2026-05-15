@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
    $titles = [
        'supplier' => ['title' => 'Supplier Aging', 'prepaid_label' => 'Prepaid to suppliers (we paid, awaiting delivery)', 'payable_label' => 'Accounts payable — suppliers (we owe them)'],
        'broker'   => ['title' => 'Broker Aging',   'prepaid_label' => 'Prepaid to customs brokers (we paid, awaiting service)', 'payable_label' => 'Accounts payable — customs brokers (we owe them)'],
    ];
    $t = $titles[$entityKind];
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write($t['title']) }}</h4>
        <small class="text-muted">{{ $lang->write('Two views: prepayments out (asset) and what we owe (liability). Bucketed by days since the last transaction with that counterparty.') }}</small>
    </div>
</div>

@php
    $sections = [
        ['title' => $lang->write($t['prepaid_label']), 'cls' => 'text-success', 'data' => $prepaid, 'kind' => 'prepaid'],
        ['title' => $lang->write($t['payable_label']), 'cls' => 'text-danger',  'data' => $payable, 'kind' => 'payable'],
    ];
@endphp

@foreach ($sections as $s)
<div class="mb-4">
    <h6 class="{{ $s['cls'] }} mb-2">{{ $s['title'] }}</h6>
    <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>{{ $lang->write(ucfirst($entityKind)) }}</th>
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
        @forelse ($s['data']['rows'] as $r)
            <tr>
                <td>{{ $r['name'] }}</td>
                <td>{{ $r['last_date'] ?? '—' }}</td>
                <td>{{ $r['days'] >= 9999 ? '—' : $r['days'] }}</td>
                <td>
                    @php
                        $bcls = match ($r['bucket']) {
                            'current' => 'bg-success',
                            'b31_60'  => 'bg-info',
                            'b61_90'  => 'bg-warning',
                            'b91_180' => 'bg-orange',
                            default   => 'bg-danger',
                        };
                    @endphp
                    <span class="badge {{ $bcls }}">{{ $buckets[$r['bucket']] }}</span>
                </td>
                @foreach ($currencies as $c)
                    @php $v = (float) $r['balances'][$c]; @endphp
                    <td class="text-end {{ abs($v) > 0.0001 ? $s['cls'] : 'text-muted' }}">
                        {{ abs($v) > 0.0001 ? $data->numberFormat($v) : '' }}
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-3">{{ $lang->write('None') }}</td></tr>
        @endforelse
        </tbody>
        <tfoot class="table-light">
            @foreach ($buckets as $bk => $label)
                <tr>
                    <td colspan="4"><strong>{{ $label }}</strong></td>
                    @foreach ($currencies as $c)
                        <td class="text-end">{{ $data->numberFormat($s['data']['totals'][$c][$bk]) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tfoot>
    </table>
    </div>
</div>
@endforeach

@endsection
