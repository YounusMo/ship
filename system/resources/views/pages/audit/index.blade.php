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
        <h4 class="h4">{{ $lang->write('Audit log') }}</h4>
        <small class="text-muted">{{ $lang->write('Append-only record of who changed what. Read-only.') }}</small>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3 col-6 mb-2">
        <label class="form-label">{{ $lang->write('From date') }}</label>
        <input type="date" class="form-control filter" data-name="from">
    </div>
    <div class="col-md-3 col-6 mb-2">
        <label class="form-label">{{ $lang->write('To date') }}</label>
        <input type="date" class="form-control filter" data-name="to">
    </div>
    <div class="col-md-2 col-6 mb-2">
        <label class="form-label">{{ $lang->write('Action') }}</label>
        <select class="form-select filter" data-name="action">
            <option value="">{{ $lang->write('All') }}</option>
        </select>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <label class="form-label">{{ $lang->write('Target table') }}</label>
        <select class="form-select filter" data-name="target_table">
            <option value="">{{ $lang->write('All') }}</option>
        </select>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <label class="form-label">{{ $lang->write('Target id') }}</label>
        <input type="number" class="form-control filter" data-name="target_id" min="1">
    </div>
</div>

<div class="main-table"></div>

@endsection
