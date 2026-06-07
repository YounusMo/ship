@php
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
    $statusBadge = [
        'scheduled' => 'bg-secondary',
        'partial'   => 'bg-warning',
        'paid'      => 'bg-success',
        'canceled'  => 'bg-dark',
    ];
@endphp
@extends('layout')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            <a href="{{url('/sourcing')}}" class="text-muted text-decoration-none">
                {{ $lang->write('Sourcing requests') }} ›
            </a>
            {{ $lang->write('Open balances') }}
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Every installment across every proforma — what clients still owe and what they have paid') }}
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('Status') }}</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">{{ $lang->write('All statuses') }}</option>
            <option value="scheduled" {{ $status === 'scheduled' ? 'selected' : '' }}>{{ $lang->write('payment.status.scheduled') }}</option>
            <option value="partial"   {{ $status === 'partial'   ? 'selected' : '' }}>{{ $lang->write('payment.status.partial') }}</option>
            <option value="paid"      {{ $status === 'paid'      ? 'selected' : '' }}>{{ $lang->write('payment.status.paid') }}</option>
            <option value="canceled"  {{ $status === 'canceled'  ? 'selected' : '' }}>{{ $lang->write('payment.status.canceled') }}</option>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('Due from') }}</label>
        <input type="date" name="from" value="{{$from}}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small text-muted">{{ $lang->write('Due to') }}</label>
        <input type="date" name="to" value="{{$to}}" class="form-control form-control-sm">
    </div>
    <div class="col-auto align-self-end">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="overdue" value="1" id="overdue" {{ $overdue ? 'checked' : '' }}>
            <label class="form-check-label" for="overdue">{{ $lang->write('Overdue only') }}</label>
        </div>
    </div>
    <div class="col-auto align-self-end">
        <button class="btn btn-primary btn-sm">{{ $lang->write('Apply') }}</button>
        <a href="{{ url('/sourcing/payments') }}" class="btn btn-outline-secondary btn-sm">{{ $lang->write('Reset') }}</a>
    </div>
</form>

{{-- Totals cards --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card border-warning">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Outstanding (per currency)') }}</div>
                @forelse ($totals['outstanding'] as $ccy => $v)
                    <div class="d-flex justify-content-between">
                        <span>{{ $ccy }}</span>
                        <strong class="text-warning">{{ number_format($v, 2) }}</strong>
                    </div>
                @empty
                    <div class="text-muted small">—</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-danger">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Overdue (per currency)') }}</div>
                @forelse ($totals['overdue'] as $ccy => $v)
                    <div class="d-flex justify-content-between">
                        <span>{{ $ccy }}</span>
                        <strong class="text-danger">{{ number_format($v, 2) }}</strong>
                    </div>
                @empty
                    <div class="text-muted small">—</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-success">
            <div class="card-body">
                <div class="text-muted small">{{ $lang->write('Collected (per currency)') }}</div>
                @forelse ($totals['paid'] as $ccy => $v)
                    <div class="d-flex justify-content-between">
                        <span>{{ $ccy }}</span>
                        <strong class="text-success">{{ number_format($v, 2) }}</strong>
                    </div>
                @empty
                    <div class="text-muted small">—</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Rows --}}
<div class="card">
    <div class="card-body">
        @if (count($rows) < 1)
            <p class="text-muted mb-0">{{ $lang->write('No installments match the current filters.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ $lang->write('Proforma') }}</th>
                            <th>{{ $lang->write('Client') }}</th>
                            <th>{{ $lang->write('Installment') }}</th>
                            <th class="text-end">{{ $lang->write('Amount') }}</th>
                            <th class="text-end">{{ $lang->write('Paid') }}</th>
                            <th class="text-end">{{ $lang->write('Outstanding') }}</th>
                            <th>{{ $lang->write('Due') }}</th>
                            <th>{{ $lang->write('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @php
                            $outstanding = (float) $r->amount - (float) $r->paid_amount;
                            $isOverdue   = $r->due_date && $r->due_date < $today && in_array($r->status, ['scheduled','partial'], true);
                        @endphp
                        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                            <td>
                                <a href="{{ url('/sourcing/' . $r->sourcing_request_id) }}" class="text-decoration-none">
                                    <code>{{ $r->request_number }}</code>
                                </a>
                                <div class="small text-muted text-truncate" style="max-width:240px;">{{ $r->title }}</div>
                            </td>
                            <td>
                                @if ($r->client_code) <span class="text-muted">{{ $r->client_code }}</span> · @endif
                                {{ $r->client_name }}
                            </td>
                            <td>
                                <span class="text-muted">#{{ $r->sequence }}</span>
                                {{ $r->label }}
                                <div class="small text-muted">{{ rtrim(rtrim(number_format((float) $r->percentage, 4, '.', ''), '0'), '.') }}%</div>
                            </td>
                            <td class="text-end">
                                {{ number_format((float) $r->amount, 2) }}
                                <span class="text-muted small">{{ strtoupper($r->currency) }}</span>
                            </td>
                            <td class="text-end">
                                {{ number_format((float) $r->paid_amount, 2) }}
                            </td>
                            <td class="text-end fw-semibold">
                                @if ($outstanding > 0.0001)
                                    <span class="{{ $isOverdue ? 'text-danger' : '' }}">{{ number_format($outstanding, 2) }}</span>
                                @else
                                    <span class="text-muted">0.00</span>
                                @endif
                            </td>
                            <td>
                                {{ $r->due_date ?? '—' }}
                                @if ($isOverdue)
                                    <div class="small text-danger">{{ $lang->write('Overdue') }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $statusBadge[$r->status] ?? 'bg-secondary' }}">
                                    {{ $lang->write('payment.status.' . $r->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ url('/sourcing/' . $r->sourcing_request_id) }}">{{ $lang->write('Open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@endsection
