@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Owners Ledger') }}</h4>
        <small class="text-muted">{{ $lang->write('Treasury movements classified as owner activity (drawings, salary, loans, capital contributions).') }}</small>
    </div>
</div>

<form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-auto">
        <label class="form-label small mb-1">{{ $lang->write('From') }}</label>
        <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">{{ $lang->write('To') }}</label>
        <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">{{ $lang->write('Owner') }}</label>
        <select name="owner_id" class="form-select form-select-sm">
            <option value="">{{ $lang->write('All') }}</option>
            @foreach ($owners as $o)
                <option value="{{ $o->id }}" {{ (string) $ownerId === (string) $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit">{{ $lang->write('Refresh') }}</button>
    </div>
</form>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>{{ $lang->write('Date') }}</th>
            <th>{{ $lang->write('Owner') }}</th>
            <th>{{ $lang->write('Purpose') }}</th>
            <th class="text-end">{{ $lang->write('Amount') }}</th>
            <th>{{ $lang->write('Currency') }}</th>
            <th>{{ $lang->write('Notes') }}</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td>{{ $r->auto_id }}</td>
            <td>{{ $r->created_date }} <span class="text-muted small">{{ $r->created_time }}</span></td>
            <td>
                @php
                    $own = $owners->firstWhere('id', $r->owner_id);
                @endphp
                {{ $own->name ?? '—' }}
            </td>
            <td>
                @php
                    $purposeClass = in_array($r->purpose, ['owner_drawing', 'owner_loan_out']) ? 'bg-warning'
                                  : ($r->purpose === 'owner_salary' ? 'bg-info'
                                  : 'bg-success');
                @endphp
                <span class="badge {{ $purposeClass }}">{{ $data->purposeLabel($r->purpose) }}</span>
            </td>
            <td class="text-end {{ $r->plus_minus === '-' ? 'text-danger' : 'text-success' }}">
                {{ $r->plus_minus }}{{ $data->numberFormat((float) $r->value) }}
            </td>
            <td>{{ strtoupper($r->currency) }}</td>
            <td class="small text-muted">{{ $r->notes }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-4">{{ $lang->write('No owner-tagged transactions in this range') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

@endsection
