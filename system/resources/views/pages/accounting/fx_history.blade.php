@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('FX Rate History') }}</h4>
        <small class="text-muted">{{ $lang->write('Every change to the EUR/LYD/CNY rates is snapshotted here. Use this when restating historical figures or reconciling FX gain/loss.') }}</small>
    </div>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>{{ $lang->write('When') }}</th>
            <th>{{ $lang->write('Currency') }}</th>
            <th class="text-end">{{ $lang->write('Previous rate') }}</th>
            <th class="text-end">{{ $lang->write('New rate') }}</th>
            <th class="text-end">{{ $lang->write('Change %') }}</th>
            <th>{{ $lang->write('Set by') }}</th>
            <th>{{ $lang->write('Notes') }}</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($rows as $r)
        @php
            $prev = (float) ($r->previous_rate ?? 0);
            $new  = (float) $r->rate;
            $pct  = $prev > 0 ? (($new - $prev) / $prev * 100) : null;
        @endphp
        <tr>
            <td>{{ $r->id }}</td>
            <td class="small text-muted">{{ $r->effective_from }}</td>
            <td><strong>{{ strtoupper($r->currency) }}</strong></td>
            <td class="text-end">{{ $r->previous_rate !== null ? number_format($prev, 4) : '—' }}</td>
            <td class="text-end">{{ number_format($new, 4) }}</td>
            <td class="text-end {{ $pct === null ? 'text-muted' : ($pct > 0 ? 'text-success' : ($pct < 0 ? 'text-danger' : '')) }}">
                {{ $pct === null ? '—' : number_format($pct, 2) . '%' }}
            </td>
            <td>{{ $r->set_by_user_name ?? '—' }}</td>
            <td class="small text-muted">{{ $r->notes ?? '' }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">{{ $lang->write('No FX rate changes recorded yet') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

@endsection
