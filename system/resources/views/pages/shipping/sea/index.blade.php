@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;

    $suppliers = DB::table('suppliers')
    ->where('deleted', 'false')
    ->where('sky_sea', 'sea')
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
    @include('pages.shipping.sea.received.new')
    @include('pages.shipping.sea.outside.exist_container')
    @include('pages.shipping.sea.outside.new_container')
    @include('pages.shipping.sea.containers.container_sea_withdraw')
    @include('pages.shipping.sea.containers.new_custom_container')
    @include('pages.shipping.sea.containers.customs')
    @include('pages.shipping.sea.containers.link')

    @if (in_array(auth()->user()->type , ['admin','branch_admin']))
        <input type="hidden" class="start_table" value="reseved">
    @endif

    @if (in_array(auth()->user()->type , ['office_work']))
        <input type="hidden" class="start_table" value="containers">
    @endif

    <div class="row d-flex align-items-center">
        <div class="col-lg-4 col-12 mb-2">
            <div class="d-flex align-items-center">
                <h4 class="h4">{{$lang->write('Sea freight')}}</h4>
                <span class="table_counter">0</span>
            </div>
        </div>
        <div class="col-lg-8 col-12 mb-2 text-end">
            <div class="d-flex align-items-center justify-content-end">
                <div class="input-group w-50">
                    <span class="input-group-text" id="basic-addon1" style="background: #f4f4f4;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                            <g fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="2">
                                <path d="M21 38c9.389 0 17-7.611 17-17S30.389 4 21 4S4 11.611 4 21s7.611 17 17 17Z" />
                                <path stroke-linecap="round" d="M26.657 14.343A7.98 7.98 0 0 0 21 12a7.98 7.98 0 0 0-5.657 2.343m17.879 18.879l8.485 8.485" />
                            </g>
                        </svg>
                    </span>
                    <input type="text" class="form-control search" placeholder="{{$lang->write('Press Enter to search')}}" style="background: #ffffff;"  aria-describedby="basic-addon1">
                </div>

                <div class="mx-2">
                    @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                        <button class="btn btn-primary show_received">{{$lang->write('Create')}}</button>
                        <button class="btn btn-primary new_container d-none">{{$lang->write('New container')}}</button>
                        <button class="btn btn-secondary insert_to_exist d-none">{{$lang->write('Insert to exist container')}}</button>
                        <button class="btn btn-primary new_custom_container d-none">{{$lang->write('New custom container')}}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card sys-nav p-0">

        <div class="">
            @if (in_array(auth()->user()->type , ['office_work']))
                <button data-tab="containers" class="sys-nav-tab active">{{$lang->write('Containers')}}</button>
            @endif
            @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                <button data-tab="received" class="sys-nav-tab active">{{$lang->write('Received')}}</button>
                <button data-tab="inside" class="sys-nav-tab">{{$lang->write('Inside')}}</button>
                <button data-tab="outside" class="sys-nav-tab">{{$lang->write('Outside')}}</button>
                <button data-tab="containers" class="sys-nav-tab">{{$lang->write('Containers')}}</button>


                <div style="float: {{auth()->user()->lang === 'ar' ? 'left' : 'right'}};margin: 0 10px;">
                    <button data-tab="canceled" class="sys-nav-tab">{{$lang->write('Canceled received')}}</button>
                    <button data-tab="canceled_containers" class="sys-nav-tab">{{$lang->write('Canceled containers')}}</button>
                </div>
            @endif
            
        </div>

    </div>
    
    <div class="main-table mt-2">
        
    </div>
@endsection