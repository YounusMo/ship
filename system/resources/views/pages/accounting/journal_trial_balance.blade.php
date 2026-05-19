@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-md-8 mb-2">
        <h4 class="h4">{{ $lang->write('Trial Balance') }}</h4>
        <small class="text-muted">{{ $lang->write('Sourced from journal_lines — totals balance by construction. P&L, Balance Sheet and Cash Flow PDFs all read from the same journal, so what you see here is what those reports add up to.') }}</small>
    </div>
    <div class="col-md-4 mb-2">
        <form method="get" class="d-flex justify-content-end gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">{{ $lang->write('As of') }}</label>
                <input type="date" name="as_of" value="{{ $asOf }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">{{ $lang->write('Refresh') }}</button>
        </form>
    </div>
</div>

@if (count($accounts) === 0)
    <div class="alert alert-light text-center text-muted">
        {{ $lang->write('No journal entries posted yet. Make a deposit/withdraw and refresh.') }}
    </div>
@else
<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th style="width:80px;">{{ $lang->write('Code') }}</th>
            <th>{{ $lang->write('Account') }}</th>
            <th class="text-end">USD DR</th><th class="text-end">USD CR</th>
            <th class="text-end">EUR DR</th><th class="text-end">EUR CR</th>
            <th class="text-end">LYD DR</th><th class="text-end">LYD CR</th>
            <th class="text-end">CNY DR</th><th class="text-end">CNY CR</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($accounts as $a)
        <tr>
            <td class="text-muted">{{ $a['code'] }}</td>
            <td>{{ $a['name'] }}</td>
            @foreach ($currencies as $c)
                <td class="text-end">{{ $a['dr'][$c] > 0.0001 ? $data->numberFormat($a['dr'][$c]) : '' }}</td>
                <td class="text-end">{{ $a['cr'][$c] > 0.0001 ? $data->numberFormat($a['cr'][$c]) : '' }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
    <tfoot class="table-light">
        <tr style="font-weight:600;">
            <td colspan="2">{{ $lang->write('Totals') }}</td>
            @foreach ($currencies as $c)
                <td class="text-end">{{ $data->numberFormat($totals['dr'][$c]) }}</td>
                <td class="text-end">{{ $data->numberFormat($totals['cr'][$c]) }}</td>
            @endforeach
        </tr>
        @php
            $balanced = true;
            foreach ($currencies as $c) {
                if (abs(($totals['dr'][$c] ?? 0) - ($totals['cr'][$c] ?? 0)) > 0.0001) { $balanced = false; break; }
            }
        @endphp
        <tr>
            <td colspan="10" class="text-center {{ $balanced ? 'text-success' : 'text-danger' }}">
                @if ($balanced)
                    ✓ {{ $lang->write('Per-currency DR = CR — books are balanced.') }}
                @else
                    ✗ {{ $lang->write('IMBALANCE detected. Use journal reverse to correct.') }}
                @endif
            </td>
        </tr>
    </tfoot>
</table>
</div>
@endif

@endsection
