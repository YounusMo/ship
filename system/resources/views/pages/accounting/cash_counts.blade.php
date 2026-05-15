@php
    $currencies = $data->currencies;
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Cash Count') }}</h4>
        <small class="text-muted">{{ $lang->write('Capture daily cash counts per branch and currency. Variance against the system balance can be posted as an adjustment.') }}</small>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="mb-3">{{ $lang->write('New count') }}</h6>
        <form id="cc-form" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ $lang->write('Branch') }}</label>
                <select name="branch_id" class="form-select form-select-sm" required>
                    <option value="">—</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">{{ $lang->write('Currency') }}</label>
                <select name="currency" class="form-select form-select-sm" required>
                    @foreach ($currencies as $c)
                        <option value="{{ $c['code'] }}">{{ $c['text'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ $lang->write('Counted amount') }}</label>
                <input type="number" step="0.01" name="counted_amount" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ $lang->write('Notes') }}</label>
                <input type="text" name="notes" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-primary w-100" type="submit">{{ $lang->write('Save') }}</button>
            </div>
        </form>
        <div id="cc-result" class="mt-2 small"></div>
    </div>
</div>

<h6 class="mb-2">{{ $lang->write('Recent counts') }}</h6>
<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>{{ $lang->write('Date') }}</th>
            <th>{{ $lang->write('Branch') }}</th>
            <th>{{ $lang->write('Currency') }}</th>
            <th class="text-end">{{ $lang->write('System') }}</th>
            <th class="text-end">{{ $lang->write('Counted') }}</th>
            <th class="text-end">{{ $lang->write('Variance') }}</th>
            <th>{{ $lang->write('By') }}</th>
            <th>{{ $lang->write('Adjusted') }}</th>
            <th>{{ $lang->write('Action') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($recent as $r)
        @php
            $cls = abs((float) $r->variance) < 0.0001 ? 'text-success' : ((float) $r->variance > 0 ? 'text-warning' : 'text-danger');
        @endphp
        <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->count_date }}</td>
            <td>{{ optional($branches->firstWhere('id', $r->branch_id))->name ?? '#'.$r->branch_id }}</td>
            <td>{{ strtoupper($r->currency) }}</td>
            <td class="text-end">{{ $data->numberFormat((float) $r->system_balance) }}</td>
            <td class="text-end">{{ $data->numberFormat((float) $r->counted_amount) }}</td>
            <td class="text-end {{ $cls }}">{{ $data->numberFormat((float) $r->variance) }}</td>
            <td>{{ $r->counted_by_user_name ?? '—' }}</td>
            <td>
                @if ($r->adjustment_posted)
                    <span class="badge bg-success">{{ $lang->write('Posted') }}</span>
                @else
                    <span class="badge bg-secondary">{{ $lang->write('Not posted') }}</span>
                @endif
            </td>
            <td>
                @if (!$r->adjustment_posted && abs((float) $r->variance) > 0.0001 && auth()->user()->type === 'admin')
                    <button class="btn btn-sm btn-warning" onclick="postAdjust({{ $r->id }})">{{ $lang->write('Post adjustment') }}</button>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

<script>
$('#cc-form').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('/accounting/cash-counts', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(j => {
            if (j.type === 'success') {
                location.reload();
            } else {
                $('#cc-result').html('<span class="text-danger">' + (j.type || 'error') + '</span>');
            }
        });
});
function postAdjust(id) {
    if (!confirm('{{ $lang->write("Post a treasury adjustment for the variance?") }}')) return;
    $.post('/accounting/cash-counts/' + id + '/adjust', { _token: '{{ csrf_token() }}' }, function() {
        location.reload();
    });
}
</script>

@endsection
