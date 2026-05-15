@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Accounting Periods') }}</h4>
        <small class="text-muted">{{ $lang->write('Close a month to prevent further inserts dated within it. Closed months can be reopened with an audit-logged reason.') }}</small>
    </div>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>{{ $lang->write('Period') }}</th>
            <th>{{ $lang->write('Range') }}</th>
            <th>{{ $lang->write('Status') }}</th>
            <th>{{ $lang->write('Closed by') }}</th>
            <th>{{ $lang->write('Closed at') }}</th>
            <th>{{ $lang->write('Action') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($periods as $p)
        <tr>
            <td>{{ $p->period_year }}-{{ str_pad($p->period_month, 2, '0', STR_PAD_LEFT) }}</td>
            <td class="text-muted small">{{ $p->period_start }} → {{ $p->period_end }}</td>
            <td>
                @if ($p->status === 'closed')
                    <span class="badge bg-danger">{{ $lang->write('Closed') }}</span>
                @else
                    <span class="badge bg-success">{{ $lang->write('Open') }}</span>
                @endif
            </td>
            <td>{{ $p->closed_by_user_name ?? '—' }}</td>
            <td class="small text-muted">{{ $p->closed_at ?? '—' }}</td>
            <td>
                @if ($p->status === 'open')
                    <button class="btn btn-sm btn-danger" onclick="closePeriod({{ $p->id }})">{{ $lang->write('Close period') }}</button>
                @else
                    <button class="btn btn-sm btn-warning" onclick="reopenPeriod({{ $p->id }})">{{ $lang->write('Reopen') }}</button>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

<script>
function closePeriod(id) {
    if (!confirm('{{ $lang->write("Close this period? Inserts dated within it will be blocked.") }}')) return;
    $.post('/accounting/periods/' + id + '/close', { _token: '{{ csrf_token() }}' }, function() {
        location.reload();
    });
}
function reopenPeriod(id) {
    const reason = prompt('{{ $lang->write("Reason for reopening?") }}');
    if (!reason) return;
    $.post('/accounting/periods/' + id + '/reopen', { _token: '{{ csrf_token() }}', reason }, function() {
        location.reload();
    });
}
</script>

@endsection
