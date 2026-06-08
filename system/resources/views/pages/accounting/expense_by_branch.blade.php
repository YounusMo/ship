@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $lang->write('Expense by branch') }}</h1>
        <div class="page-subtitle">
            {{ $lang->write('Expense grouped by branch_id from journal_lines') }} ·
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
        @if(empty($branches))
            <div class="alert alert-info">{{ $lang->write('No expense in this period.') }}</div>
        @else
            <table class="table table-bordered table-striped" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>{{ $lang->write('Branch') }}</th>
                        @foreach($currencies as $c)
                            <th class="text-end">{{ strtoupper($c) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php $totals = array_fill_keys($currencies, 0.0); @endphp
                    @foreach($branches as $br)
                        <tr>
                            <td>
                                {{ $br['name'] }}
                                @if(empty($br['id']))
                                    <span class="badge bg-warning text-dark" title="{{ $lang->write('Expense without branch_id — fix at source per the cardinal rule') }}">
                                        {{ $lang->write('unassigned') }}
                                    </span>
                                @endif
                            </td>
                            @foreach($currencies as $c)
                                @php $v = $br['by_currency'][$c] ?? 0.0; $totals[$c] += $v; @endphp
                                <td class="text-end {{ $v < 0 ? 'text-danger' : '' }}">
                                    {{ $v == 0 ? '—' : number_format($v, 2) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr style="border-top:2px solid #333;font-weight:600;">
                        <td>{{ $lang->write('Total') }}</td>
                        @foreach($currencies as $c)
                            <td class="text-end">{{ number_format($totals[$c], 2) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
</div>

@endsection
