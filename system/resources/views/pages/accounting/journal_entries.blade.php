@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-md-8 mb-2">
        <h4 class="h4">{{ $lang->write('Journal Entries') }}</h4>
        <small class="text-muted">{{ $lang->write('Every cash-affecting mutation appends a balanced DR/CR entry here. Reversed entries are kept (append-only) but excluded from the trial balance.') }}</small>
    </div>
    <div class="col-md-4 mb-2">
        <form method="get" class="d-flex justify-content-end gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">{{ $lang->write('From') }}</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">{{ $lang->write('To') }}</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">{{ $lang->write('Refresh') }}</button>
        </form>
    </div>
</div>

@if ($entries->isEmpty())
    <div class="alert alert-light text-center text-muted">
        {{ $lang->write('No entries in this range.') }}
    </div>
@else
<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>{{ $lang->write('Date') }}</th>
            <th>{{ $lang->write('Kind') }}</th>
            <th>{{ $lang->write('Description') }}</th>
            <th>{{ $lang->write('Status') }}</th>
            <th class="text-end">{{ $lang->write('Lines') }}</th>
            <th>{{ $lang->write('By') }}</th>
            <th>{{ $lang->write('Action') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($entries as $e)
        @php $lines = $linesByEntry[$e->id] ?? []; @endphp
        <tr>
            <td class="text-muted">{{ $e->id }}</td>
            <td>{{ $e->entry_date }}</td>
            <td><span class="badge bg-secondary">{{ $e->kind }}</span></td>
            <td class="small">{{ $e->description }}</td>
            <td>
                @if ($e->status === 'reversed')
                    <span class="badge bg-danger">{{ $lang->write('Reversed') }} → #{{ $e->reversed_by_entry_id }}</span>
                @else
                    <span class="badge bg-success">{{ $lang->write('Open') }}</span>
                @endif
                @if ($e->reverses_entry_id)
                    <span class="badge bg-warning">{{ $lang->write('Reverses') }} #{{ $e->reverses_entry_id }}</span>
                @endif
            </td>
            <td class="text-end">{{ count($lines) }}</td>
            <td class="small">{{ $e->posted_by_user_name }}</td>
            <td>
                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleLines({{ $e->id }})">{{ $lang->write('Lines') }}</button>
                @if ($e->status === 'open')
                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="reverseEntry({{ $e->id }})">{{ $lang->write('Reverse') }}</button>
                @endif
            </td>
        </tr>
        <tr id="lines-{{ $e->id }}" style="display:none; background:#fafbfc;">
            <td colspan="8">
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>{{ $lang->write('Account') }}</th><th class="text-end">DR</th><th class="text-end">CR</th><th>{{ $lang->write('CCY') }}</th><th>{{ $lang->write('Counterparty') }}</th><th>{{ $lang->write('Description') }}</th></tr></thead>
                    <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>{{ $l->line_no }}</td>
                            <td>{{ $l->account_code }} — {{ $l->account_name }}</td>
                            <td class="text-end {{ $l->dr > 0 ? 'text-primary' : 'text-muted' }}">{{ $l->dr > 0 ? $data->numberFormat((float)$l->dr) : '' }}</td>
                            <td class="text-end {{ $l->cr > 0 ? 'text-success' : 'text-muted' }}">{{ $l->cr > 0 ? $data->numberFormat((float)$l->cr) : '' }}</td>
                            <td>{{ strtoupper($l->currency) }}</td>
                            <td class="small text-muted">{{ $l->counterparty_type }}#{{ $l->counterparty_id }}</td>
                            <td class="small text-muted">{{ $l->description }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif

<script>
function toggleLines(id) {
    const el = document.getElementById('lines-' + id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}
function reverseEntry(id) {
    const reason = prompt('{{ $lang->write("Reason for reversal?") }}');
    if (!reason) return;
    $.post('/accounting/journal-entries/' + id + '/reverse',
        { _token: '{{ csrf_token() }}', reason },
        function() { location.reload(); }
    );
}
</script>

@endsection
