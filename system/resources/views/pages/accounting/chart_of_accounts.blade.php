@php
    if (!in_array(auth()->user()->type, ['admin'])) { abort(403); }
@endphp
@extends('layout')
@section('content')

<div class="row mb-3 align-items-end">
    <div class="col-12 mb-2">
        <h4 class="h4">{{ $lang->write('Chart of Accounts') }}</h4>
        <small class="text-muted">{{ $lang->write('The standard accounts the trial balance derives from. System accounts cannot be removed.') }}</small>
    </div>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th style="width:90px;">{{ $lang->write('Code') }}</th>
            <th>{{ $lang->write('Account') }}</th>
            <th>{{ $lang->write('Type') }}</th>
            <th>{{ $lang->write('Normal balance') }}</th>
            <th>{{ $lang->write('Status') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($accounts as $a)
        <tr>
            <td class="text-muted">{{ $a->code }}</td>
            <td>{{ $lang->write($a->name) }}</td>
            <td><span class="badge bg-secondary">{{ $lang->write(ucfirst($a->type)) }}</span></td>
            <td>{{ ucfirst($a->normal_balance) }}</td>
            <td>
                @if ($a->is_system) <span class="badge bg-info">{{ $lang->write('System') }}</span> @endif
                @if (!$a->is_active) <span class="badge bg-warning">{{ $lang->write('Inactive') }}</span> @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

@endsection
