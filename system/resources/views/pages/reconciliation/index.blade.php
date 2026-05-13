@php
    use App\Http\Controllers\langController;
    $lang = new langController();
    if (!in_array(auth()->user()->type, ['admin'])) {
        abort(403, 'Unauthorized');
    }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Reconciliation') }}</h4>
        <small class="text-muted">{{ $lang->write('Compares the cached running balance against the sum of transactions. Discrepancies are flagged for investigation.') }}</small>
    </div>
</div>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-clients" type="button">
            {{ $lang->write('Clients') }}
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-branches" type="button">
            {{ $lang->write('Branches') }}
        </button>
    </li>
</ul>

<div class="row my-3">
    <div class="col-md-6 mb-2">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="only_diff" checked>
            <label class="form-check-label" for="only_diff">
                {{ $lang->write('Show only rows with a discrepancy') }}
            </label>
        </div>
    </div>
</div>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-clients" role="tabpanel">
        <div class="main-table-clients"></div>
    </div>
    <div class="tab-pane fade" id="tab-branches" role="tabpanel">
        <div class="main-table-branches"></div>
    </div>
</div>

@endsection
