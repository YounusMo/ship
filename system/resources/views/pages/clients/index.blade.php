@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;

    $bl = [];

    foreach ($currencies as $key => $value) {
        $pos = DB::table('clients')->select('balance_'.$value['code'])->where('deleted','false')->where('balance_'.$value['code'],'>',0)->sum('balance_'.$value['code']);
        $neg = DB::table('clients')->select('balance_'.$value['code'])->where('deleted','false')->where('balance_'.$value['code'],'<',0)->sum('balance_'.$value['code']);
        $dif = $pos - abs($neg);
        $bl[$value['code']] = [$pos , $neg , $dif];
    }

@endphp
@extends('layout')
@section('content')

    @include('pages.clients.new')
    @include('pages.clients.deposit')
    @include('pages.clients.withdraw')
    @include('pages.clients.withdraw_commission')
    @include('pages.clients.transfer')
    @include('pages.clients.transfer_clients')
    @include('pages.clients.reports')
    @include('pages.clients.all_reports')
    @include('pages.clients.pending')

    {{-- ============ Page header ============ --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                {{ $lang->write('Clients') }}
                <span class="table_counter text-muted" style="font-size:var(--fs-lg);font-weight:500;margin-inline-start:8px;">0</span>
            </h1>
            <div class="page-subtitle">
                {{ $lang->write('Manage client balances, deposits and withdrawals') }}
            </div>
        </div>
        <div class="page-actions">
            @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                <button class="btn btn-secondary reset_filters">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12S6.477 2 12 2v2a8 8 0 1 0 4.5 1.385V8h-2V2h6v2H18a9.99 9.99 0 0 1 4 8" />
                    </svg>
                    {{ $lang->write('Reset') }}
                </button>
                <button class="btn btn-primary new">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <g fill="none">
                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" />
                            <path fill="currentColor" fill-rule="evenodd" d="M15.276 16a11 11 0 0 0-4.37-.446c-1.64.162-3.191.686-4.456 1.517c-1.264.832-2.196 1.943-2.648 3.208a.5.5 0 1 0 .941.336C5.11 19.588 5.885 18.64 7 17.907s2.508-1.21 4.005-1.358c.55-.054 1.103-.063 1.649-.028A2 2 0 0 1 14 16z" clip-rule="evenodd" />
                            <path stroke="currentColor" stroke-linecap="round" d="M18 14v8m4-4h-8" stroke-width="1.5" />
                        </g>
                    </svg>
                    {{ $lang->write('New client') }}
                </button>
            @endif
            @if (auth()->user()->type === 'admin')
                <button type="button" class="btn btn-danger delete" data-table='{{$page}}'>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m18 9l-.84 8.398c-.127 1.273-.19 1.909-.48 2.39a2.5 2.5 0 0 1-1.075.973C15.098 21 14.46 21 13.18 21h-2.36c-1.279 0-1.918 0-2.425-.24a2.5 2.5 0 0 1-1.076-.973c-.288-.48-.352-1.116-.48-2.389L6 9m7.5 6.5v-5m-3 5v-5m-6-4h4.615m0 0l.386-2.672c.112-.486.516-.828.98-.828h3.038c.464 0 .867.342.98.828l.386 2.672m-5.77 0h5.77m0 0H19.5" />
                    </svg>
                    {{ $lang->write('Delete') }}
                </button>
            @endif
        </div>
    </div>

    {{-- ============ Balance summary tiles ============ --}}
    <div class="kpi-grid">
        @foreach ($currencies as $item)
            @php $b = $bl[$item['code']] ?? [0,0,0]; @endphp
            <div class="kpi-tile">
                <div class="kpi-label">{{ strtoupper($item['code']) }} {{ $lang->write('net') }}</div>
                <div class="kpi-value {{ $b[2] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $dataController->numberFormat($b[2]) }}
                </div>
                <div class="kpi-sub d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge-finance positive">+{{ $dataController->numberFormat($b[0]) }}</span>
                    <span class="badge-finance negative">{{ $dataController->numberFormat($b[1]) }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============ Toolbar ============ --}}
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
            <button class="btn btn-secondary btn-sm get_positive">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="margin-inline-end:4px;vertical-align:-2px"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14" /></svg>
                {{ $lang->write('Positive') }}
            </button>
            <button class="btn btn-secondary btn-sm get_negative">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="margin-inline-end:4px;vertical-align:-2px"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" /></svg>
                {{ $lang->write('Negative') }}
            </button>
            <button class="btn btn-gold btn-sm get_pending">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="margin-inline-end:4px;vertical-align:-2px"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M12 22a10 10 0 1 1 0-20a10 10 0 0 1 0 20" /></svg>
                {{ $lang->write('Pending') }}
            </button>

            <span class="in_trash d-none">
                <button class="btn btn-secondary btn-sm show_trash" data-table='{{$page}}'>{{$lang->write('Back')}}</button>
                <button class="btn btn-success btn-sm restore" data-table='{{$page}}'>{{$lang->write('Restore')}}</button>
                <button class="btn btn-danger btn-sm delete_permanent" data-table='{{$page}}'>{{$lang->write('Permanent deletion')}}</button>
            </span>
        </div>
    </div>

    {{-- ============ Main table ============ --}}
    <div class="main-table"></div>

@endsection
