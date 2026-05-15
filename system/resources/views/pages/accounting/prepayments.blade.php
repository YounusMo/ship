@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Prepayments') }}</h4>
        <small class="text-muted">{{ $lang->write('Client deposits with the "Prepayment received" purpose can be tracked here and partially applied to future transactions.') }}</small>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $status === 'open' ? 'active' : '' }}" href="?status=open">{{ $lang->write('Open') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'fully_applied' ? 'active' : '' }}" href="?status=fully_applied">{{ $lang->write('Fully applied') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'all' ? 'active' : '' }}" href="?status=all">{{ $lang->write('All') }}</a>
    </li>
</ul>

@if ($dangling->count() > 0)
<div class="alert alert-warning">
    <strong>{{ $lang->write('Unregistered prepayments') }}:</strong>
    {{ $lang->write('The following deposits were marked as prepayments but are not yet registered for tracking.') }}
    <table class="table table-sm mt-2 mb-0">
        <thead><tr><th>#</th><th>{{ $lang->write('Client') }}</th><th>{{ $lang->write('Date') }}</th><th class="text-end">{{ $lang->write('Amount') }}</th><th></th></tr></thead>
        <tbody>
        @foreach ($dangling as $d)
            <tr>
                <td>{{ $d->auto_id }}</td>
                <td>{{ $d->client_code }} — {{ $d->client_name }}</td>
                <td>{{ $d->created_date }}</td>
                <td class="text-end">{{ $data->numberFormat((float) $d->value) }} {{ strtoupper($d->currency) }}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="registerPrepayment({{ $d->id }})">{{ $lang->write('Register') }}</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>{{ $lang->write('Client') }}</th>
            <th>{{ $lang->write('Received') }}</th>
            <th>{{ $lang->write('Currency') }}</th>
            <th class="text-end">{{ $lang->write('Original') }}</th>
            <th class="text-end">{{ $lang->write('Applied') }}</th>
            <th class="text-end">{{ $lang->write('Remaining') }}</th>
            <th>{{ $lang->write('Status') }}</th>
            <th>{{ $lang->write('Action') }}</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->client_code }} — {{ $r->client_name }}</td>
            <td>{{ $r->received_date }}</td>
            <td>{{ strtoupper($r->currency) }}</td>
            <td class="text-end">{{ $data->numberFormat((float) $r->original_amount) }}</td>
            <td class="text-end">{{ $data->numberFormat((float) $r->applied_amount) }}</td>
            <td class="text-end"><strong>{{ $data->numberFormat((float) $r->remaining_amount) }}</strong></td>
            <td>
                @if ($r->status === 'open') <span class="badge bg-warning">{{ $lang->write('Open') }}</span>
                @elseif ($r->status === 'fully_applied') <span class="badge bg-success">{{ $lang->write('Fully applied') }}</span>
                @else <span class="badge bg-secondary">{{ $r->status }}</span> @endif
            </td>
            <td>
                @if ($r->status === 'open')
                    <button class="btn btn-sm btn-primary" onclick="applyPrepayment({{ $r->id }}, {{ (float) $r->remaining_amount }})">{{ $lang->write('Apply') }}</button>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center text-muted py-4">{{ $lang->write('No prepayments') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

<script>
function registerPrepayment(txnId) {
    $.post('/accounting/prepayments/register', { _token: '{{ csrf_token() }}', source_transaction_id: txnId }, function() {
        location.reload();
    });
}
function applyPrepayment(id, remaining) {
    const amountStr = prompt('{{ $lang->write("Amount to apply (max") }} ' + remaining + ')');
    if (!amountStr) return;
    const amount = parseFloat(amountStr);
    if (isNaN(amount) || amount <= 0) { alert('Invalid'); return; }
    const ref = prompt('{{ $lang->write("Applied to (container number, transaction ref, etc.)") }}');
    $.post('/accounting/prepayments/' + id + '/apply', {
        _token: '{{ csrf_token() }}',
        amount, applied_to_ref: ref,
    }, function(j) {
        if (j.type === 'success') location.reload();
        else alert(j.type || 'error');
    });
}
</script>

@endsection
