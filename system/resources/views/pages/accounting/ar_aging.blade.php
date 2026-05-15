@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Client Aging') }}</h4>
        <small class="text-muted">{{ $lang->write('Two views: receivables (clients who owe us) and client deposits we hold (we owe them service). Bucketed by days since the client\'s last transaction.') }}</small>
    </div>
</div>

@php
    $sections = [
        [
            'title'   => $lang->write('Accounts receivable — clients owe us'),
            'cls'     => 'text-danger',
            'note'    => $lang->write('These clients have negative balances (overdrawn). Amounts shown as absolute values.'),
            'data'    => $receivables,
            'kind'    => 'recv',
        ],
        [
            'title'   => $lang->write('Client deposits we hold'),
            'cls'     => 'text-warning',
            'note'    => $lang->write('Positive balances — money the client has on deposit that we owe back as service. Aged by last activity to surface dormant prepayments.'),
            'data'    => $deposits,
            'kind'    => 'dep',
        ],
    ];
@endphp

@foreach ($sections as $s)
<div class="mb-4">
    <h6 class="{{ $s['cls'] }} mb-1">{{ $s['title'] }}</h6>
    <p class="small text-muted mb-2">{{ $s['note'] }}</p>

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
        @forelse ($s['data']['rows'] as $r)
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
