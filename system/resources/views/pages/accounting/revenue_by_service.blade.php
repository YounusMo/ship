@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $lang->write('Revenue by service') }}</h1>
        <div class="page-subtitle">
            {{ $lang->write('Revenue breakdown per service line for the selected period') }} ·
            <strong>{{ $range_label }}</strong>
        </div>
    </div>
    <form method="get" class="page-actions" style="display:flex;gap:.5rem;align-items:center;">
        <label>{{ $lang->write('From') }}
            <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm" style="width:auto;display:inline-block;">
        </label>
        <label>{{ $lang->write('To') }}
            <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm" style="width:auto;display:inline-block;">
        </label>
        <button class="btn btn-primary btn-sm">{{ $lang->write('Refresh') }}</button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        @if(empty($services))
            <div class="alert alert-info">
                {{ $lang->write('No revenue in this period.') }}
            </div>
        @else
            <table class="table table-bordered table-striped" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>{{ $lang->write('Service') }}</th>
                        @foreach($currencies as $c)
                            <th class="text-end">{{ strtoupper($c) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php $totals = array_fill_keys($currencies, 0.0); @endphp
                    @foreach($services as $svc)
                        <tr>
                            <td>{{ $svc['name'] }} <small class="text-muted">({{ $svc['code'] }})</small></td>
                            @foreach($currencies as $c)
                                @php $v = $svc['by_currency'][$c] ?? 0.0; $totals[$c] += $v; @endphp
                                <td class="text-end {{ $v < 0 ? 'text-danger' : '' }}">
                                    {{ $v == 0 ? '—' : number_format($v, 2) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr style="border-top:2px solid #333;font-weight:600;">
                        <td>{{ $lang->write('Total revenue') }}</td>
                        @foreach($currencies as $c)
                            <td class="text-end">{{ number_format($totals[$c], 2) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>

            <h5 style="margin-top:1.5rem;">{{ $lang->write('FX gain (account 4200) — reported separately') }}</h5>
            <table class="table table-bordered" style="margin-bottom:0;">
                <thead>
                    <tr>
                        @foreach($currencies as $c)
                            <th class="text-end">{{ strtoupper($c) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach($currencies as $c)
                            @php $v = $fx_gain[$c] ?? 0.0; @endphp
                            <td class="text-end {{ $v < 0 ? 'text-danger' : '' }}">
                                {{ $v == 0 ? '—' : number_format($v, 2) }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
</div>

@endsection
