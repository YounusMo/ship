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

@if(!empty($prefilter ?? null))
    <script>
        window.sourcingPrefilter = @json($prefilter);
    </script>
    @if(!empty($prefilter['client_id']))
        <div class="alert alert-info d-flex align-items-center justify-content-between" style="margin-bottom: 1rem;">
            <div>
                <strong>{{ $lang->write('Filtered to client') }}:</strong>
                #{{ $prefilter['client_code'] }} — {{ $prefilter['client_name'] }}
            </div>
            <a href="{{ url('/sourcing' . (($view ?? 'all') !== 'all' ? '?view=' . $view : '')) }}" class="btn btn-sm btn-outline-secondary">
                {{ $lang->write('Clear filter') }}
            </a>
        </div>
    @endif
@endif

@php
    $currentView = $view ?? 'all';
    $headerTitle = $currentView === 'proformas'
        ? $lang->write('Proformas')
        : ($currentView === 'requests'
            ? $lang->write('Sourcing requests')
            : $lang->write('All sourcing & proformas'));
    $headerSub = $currentView === 'proformas'
        ? $lang->write('Quoted commercial documents — sent / accepted / fulfilled')
        : ($currentView === 'requests'
            ? $lang->write('Discovery stage — open or being searched')
            : $lang->write('Combined view across every status'));
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">
            {{ $headerTitle }}
            <span class="table_counter text-muted" style="font-size:var(--fs-lg);font-weight:500;margin-inline-start:8px;">0</span>
        </h1>
        <div class="page-subtitle">
            {{ $headerSub }}
        </div>
        <div style="margin-top:.5rem;display:flex;gap:.4rem;">
            <a href="{{ url('/sourcing?view=requests') }}"
               class="btn btn-sm {{ $currentView === 'requests' ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $lang->write('Sourcing requests') }}
            </a>
            <a href="{{ url('/sourcing?view=proformas') }}"
               class="btn btn-sm {{ $currentView === 'proformas' ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $lang->write('Proformas') }}
            </a>
            <a href="{{ url('/sourcing') }}"
               class="btn btn-sm {{ $currentView === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $lang->write('All') }}
            </a>
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
