@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;

    $suppliers = DB::table('suppliers')
    ->where('deleted', 'false')
    ->where('sky_sea', 'sky')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($suppliers)use($lang) {
        return [
          'val' => (string) $suppliers->id,
          'txt' => $suppliers->name,
        ];
    })
  ->toArray();
  
@endphp
@extends('layout')
@section('content')
    @include('pages.shipping.sky.received.new')
    @include('pages.shipping.sky.outside.new_container')
    @include('pages.shipping.sky.containers.container_sea_withdraw')
    @include('pages.shipping.sky.outside.exist_container')
    {{-- @include('pages.shipping.sky.containers.new_custom_container') --}}
    @include('pages.shipping.sky.containers.customs')
    @include('pages.shipping.sky.containers.link')

    @if (in_array(auth()->user()->type , ['admin','branch_admin']))
        <input type="hidden" class="start_table" value="reseved">
    @endif

    @if (in_array(auth()->user()->type , ['office_work']))
        <input type="hidden" class="start_table" value="containers">
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">
                {{ $lang->write('Air freight') }}
                <span class="table_counter text-muted" style="font-size:var(--fs-lg);font-weight:500;margin-inline-start:8px;">0</span>
            </h1>
            <div class="page-subtitle">
                {{ $lang->write('Trips, received goods and outbound shipments') }}
            </div>
        </div>
        <div class="page-actions">
            @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                <button class="btn btn-primary show_received">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    {{ $lang->write('Create') }}
                </button>
                <button class="btn btn-primary new_container d-none">{{ $lang->write('New trip') }}</button>
                <button class="btn btn-secondary insert_to_exist d-none">{{ $lang->write('Insert to exist trip') }}</button>
            @endif
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
    </div>

    <div class="card sys-nav p-0">
        <div class="">
            @if (in_array(auth()->user()->type , ['office_work']))
                <button data-tab="containers" class="sys-nav-tab active">{{$lang->write('Trips')}}</button>
            @endif
            @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                <button data-tab="received" class="sys-nav-tab active">{{$lang->write('Received')}}</button>
                <button data-tab="inside" class="sys-nav-tab">{{$lang->write('Inside')}}</button>
                <button data-tab="outside" class="sys-nav-tab">{{$lang->write('Outside')}}</button>
                <button data-tab="containers" class="sys-nav-tab">{{$lang->write('Trips')}}</button>


                <div style="float: {{auth()->user()->lang === 'ar' ? 'left' : 'right'}};margin: 0 10px;">
                    <button data-tab="canceled" class="sys-nav-tab">{{$lang->write('Canceled received')}}</button>
                    <button data-tab="canceled_containers" class="sys-nav-tab">{{$lang->write('Canceled trips')}}</button>
                </div>
            @endif

           
        </div>
    </div>
    
    <div class="main-table mt-2">
        
    </div>
@endsection