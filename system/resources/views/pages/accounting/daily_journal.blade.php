@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-md-8 mb-2">
        <h4 class="h4">{{ $lang->write('Daily Journal') }}</h4>
        <small class="text-muted">{{ $lang->write('Every client, treasury, supplier, and broker transaction on the selected date, in chronological order. Use it for end-of-day review.') }}</small>
    </div>
    <div class="col-md-4 mb-2">
        <form method="get" class="d-flex justify-content-end gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">{{ $lang->write('Date') }}</label>
                <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">{{ $lang->write('Refresh') }}</button>
        </form>
    </div>
</div>

@if (count($rows) === 0)
    <div class="alert alert-light text-center text-muted">{{ $lang->write('No transactions for this date.') }}</div>
@else
<div class="row mb-3">
    @foreach (['usd' => 'USD', 'eur' => 'EUR', 'den' => 'LYD', 'cny' => 'CNY'] as $c => $label)
        <div class="col-md-3 mb-2">
            <div class="card">
                <div class="card-body py-2 px-3">
                    <div class="small text-muted">{{ $label }} {{ $lang->write('treasury net change') }}</div>
                    <div class="{{ $totals[$c] > 0.0001 ? 'text-success' : ($totals[$c] < -0.0001 ? 'text-danger' : 'text-muted') }} fs-5 fw-bold">
                        {{ $totals[$c] > 0 ? '+' : '' }}{{ $data->numberFormat($totals[$c]) }}
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>{{ $lang->write('Time') }}</th>
            <th>{{ $lang->write('Source') }}</th>
            <th>{{ $lang->write('Party') }}</th>
            <th>{{ $lang->write('Type') }}</th>
            <th>{{ $lang->write('Purpose') }}</th>
            <th class="text-end">{{ $lang->write('Amount') }}</th>
            <th>{{ $lang->write('CCY') }}</th>
            <th>{{ $lang->write('Branch') }}</th>
            <th>{{ $lang->write('Notes') }}</th>
            <th>{{ $lang->write('By') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($rows as $r)
        @php
            $sourceCls = match ($r['source']) {
                'client'   => 'bg-primary',
                'branch'   => 'bg-info',
                'supplier' => 'bg-warning',
                'broker'   => 'bg-secondary',
                default    => 'bg-light',
            };
            $isInflow = ($r['sign'] === 'plus' || $r['sign'] === '+');
        @endphp
        <tr>
            <td class="text-muted">{{ $r['auto_id'] ?? '—' }}</td>
            <td class="small text-muted">{{ substr($r['time'] ?? '', 0, 5) }}</td>
            <td><span class="badge {{ $sourceCls }}">{{ $lang->write(ucfirst($r['source'])) }}</span></td>
            <td>{{ $r['party'] }}</td>
            <td>{{ $data->get_type($r['type']) ?? $r['type'] }}</td>
            <td>
                @if (!empty($r['purpose']))
                    <span class="badge bg-light text-dark">{{ $data->purposeLabel($r['purpose']) }}</span>
                @endif
            </td>
            <td class="text-end {{ $isInflow ? 'text-success' : 'text-danger' }}">
                {{ $isInflow ? '+' : '−' }}{{ $data->numberFormat($r['value']) }}
            </td>
            <td>{{ strtoupper($r['currency']) }}</td>
            <td class="small text-muted">{{ $branchNames[$r['branch']] ?? ('#'.$r['branch']) }}</td>
            <td class="small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $r['notes'] }}">{{ $r['notes'] }}</td>
            <td class="small text-muted">{{ $users[$r['user_id']] ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif

@endsection
