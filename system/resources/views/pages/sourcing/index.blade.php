@php
    use App\Http\Controllers\langController;

    $lang = new langController();
    if (!in_array(auth()->user()->type, ['admin', 'branch_admin'])) {
        abort(403, 'Unauthorized');
    }
@endphp
@extends('layout')
@section('content')

@include('pages.sourcing.new')

<div class="page-header">
    <div>
        <h1 class="page-title">
            {{ $lang->write('Sourcing requests') }}
            <span class="table_counter text-muted" style="font-size:var(--fs-lg);font-weight:500;margin-inline-start:8px;">0</span>
        </h1>
        <div class="page-subtitle">
            {{ $lang->write('Find-goods requests with commission tracking') }}
        </div>
    </div>
    <div class="page-actions">
        <button class="btn btn-outline-primary bulk-pdf-btn" onclick="exportSelectedPdf()" style="display:none;">
            {{ $lang->write('Export as PDF') }} <span class="badge bg-light text-dark selected-count">0</span>
        </button>
        <button class="btn btn-outline-secondary bulk-trash-btn" onclick="bulkTrashSelected()" style="display:none;">
            🗑 {{ $lang->write('Trash') }} <span class="badge bg-light text-dark selected-count">0</span>
        </button>
        <button class="btn btn-outline-success bulk-restore-btn" onclick="bulkRestoreSelected()" style="display:none;">
            ↺ {{ $lang->write('Restore') }} <span class="badge bg-light text-dark selected-count">0</span>
        </button>
        <a class="btn btn-outline-secondary" href="{{url('/sourcing/dashboard')}}">
            {{ $lang->write('Dashboard') }}
        </a>
        <a class="btn btn-outline-secondary" href="{{url('/sourcing/board')}}">
            {{ $lang->write('Board') }}
        </a>
        <a class="btn btn-outline-secondary" href="{{url('/sourcing/funnel')}}">
            {{ $lang->write('Funnel') }}
        </a>
        <a class="btn btn-outline-secondary" href="{{url('/sourcing/catalog/manage')}}">
            {{ $lang->write('Catalog') }}
        </a>
        <a class="btn btn-outline-secondary" href="{{url('/sourcing/insights/suppliers')}}">
            {{ $lang->write('Suppliers') }}
        </a>
        <a class="btn btn-outline-secondary" href="{{url('/sourcing/payments')}}">
            {{ $lang->write('Open balances') }}
        </a>
        <a class="btn btn-outline-secondary" href="{{url('/sourcing/commissions')}}">
            {{ $lang->write('Commissions report') }}
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#new">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            {{ $lang->write('New') }}
        </button>
    </div>
</div>

<div class="toolbar">
    <div class="toolbar-search">
        <div class="search-input">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" class="form-control search" placeholder="{{ $lang->write('Press Enter to search') }}">
        </div>
    </div>
    <div class="toolbar-actions">
        <select class="form-select status_filter" style="width:auto;">
            <option value="">{{ $lang->write('All statuses') }}</option>
            <option value="open">{{ $lang->write('sourcing.status.open') }}</option>
            <option value="searching">{{ $lang->write('sourcing.status.searching') }}</option>
            <option value="quoted">{{ $lang->write('sourcing.status.quoted') }}</option>
            <option value="accepted">{{ $lang->write('sourcing.status.accepted') }}</option>
            <option value="fulfilled">{{ $lang->write('sourcing.status.fulfilled') }}</option>
            <option value="canceled">{{ $lang->write('sourcing.status.canceled') }}</option>
        </select>
        <button class="btn btn-outline-secondary toggle-trash" type="button">
            {{ $lang->write('Show trash') }}
        </button>
    </div>
</div>

<div class="main-table"></div>

<div class="ajax_elements"></div>

@endsection
