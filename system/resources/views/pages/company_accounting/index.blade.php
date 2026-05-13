@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\clientsController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();
    $clientsController = new clientsController();

    $currencies = $dataController->currencies;


    $branchesX = DB::table('branches');
    $branchesX = $branchesX->where('deleted', 'false');
    if (in_array(auth()->user()->type , ['branch_admin'])) {
        $branchesX = $branchesX->where('id', auth()->user()->branch);
    }
    $branchesX = $branchesX->orderBy('id', 'DESC');
    $branchesX = $branchesX->get();
    $branchesX = $branchesX->map(function ($branch)use($lang) {
        return [
          'val' => (string) $branch->id,
          'txt' => $lang->branch($branch->id),
        ];
    })
    ->toArray();

    $branches = DB::table('branches');
    $branches = $branches->where('deleted', 'false');
    $branches = $branches->whereNot('id', 15);
    if (in_array(auth()->user()->type , ['branch_admin'])) {
        $branches = $branches->where('id', auth()->user()->branch);
    }
    $branches = $branches->orderBy('id', 'DESC');
    $branches = $branches->get();
    $branches = $branches->map(function ($branch)use($lang) {
        return [
          'val' => (string) $branch->id,
          'txt' => $lang->branch($branch->id),
        ];
    })
    ->toArray();



    $branches = DB::table('branches');
    $branches = $branches->where('deleted', 'false');
    $branches = $branches->whereNot('id', 15);
    if (in_array(auth()->user()->type , ['branch_admin'])) {
        $branches = $branches->where('id', auth()->user()->branch);
    }
    $branches = $branches->orderBy('id', 'DESC');
    $branches = $branches->get();
    $branches = $branches->map(function ($branch)use($lang) {
        return [
          'val' => (string) $branch->id,
          'txt' => $lang->branch($branch->id),
        ];
    })
    ->toArray();

    $branch_name = $lang->write('All');
    $branch_id = '';
    if(in_array(auth()->user()->type , ['branch_admin'])){
        $branch_id = auth()->user()->branch;
        $get_ = DB::table('branches')->where('id',auth()->user()->branch)->first();
        if($get_){
            $branch_name = $lang->branch($get_->id);    
        }
    }

    $clients  = DB::table('clients')->where('deleted','false')->get();

    $usd_p = 0;
    $eur_p = 0;
    $den_p = 0;
    $cny_p = 0;

    $usd_m = 0;
    $eur_m = 0;
    $den_m = 0;
    $cny_m = 0;

    foreach ($clients as $key => $value) {
        $cl = $clientsController->search_client_balance($value->id , null, null);

        if($cl[0] < 0){
            $usd_m += ($cl[0]);
        }
        
        if($cl[0] > 0){
            $usd_p += $cl[0];
        }

        if($cl[1] < 0){
            $eur_m += ($cl[1]);
        }
        if($cl[1] > 0){
            $eur_p += $cl[1];
        }

        if($cl[2] < 0){
            $cny_m += ($cl[2]);
        }

        if($cl[2] > 0){
            $cny_p += $cl[2];
        }

        if($cl[3] < 0){
            $den_m += ($cl[3]);
        }

        if($cl[3] > 0){
            $den_p += $cl[3];
        }
    }
@endphp
@extends('layout')
@section('content')

    @include('pages.company_accounting.deposit_branch')
    @include('pages.company_accounting.add_expenses')
    @include('pages.company_accounting.transfer')
    @include('pages.company_accounting.fix_branch')
    @include('pages.company_accounting.container_sea_withdraw')
    @include('pages.company_accounting.container_sky_withdraw')
    @include('pages.company_accounting.commission_branch')

    <div class="row d-flex align-items-center">
        <div class="col-lg-4 col-12 mb-2">
            <div class="d-flex align-items-center">
                <h4 class="h4">{{$lang->write('Accounting')}}</h4>
                <span class="table_counter">0</span>
            </div>
        </div>
        <div class="col-lg-8 col-12 mb-2 text-end">
            <div class="d-flex align-items-center justify-content-end">
                <div class="w-25 text-start branch">
                    <label for="">{{$lang->write('Treasury')}} :</label>
                    {!! $dataController->sys_selector('branch',$branches , $branch_id ,in_array(auth()->user()->type , ['branch_admin']) ? false: true , $branch_name) !!}
                </div>
                <div class="w-25 text-start mx-2">
                    <label for="">{{$lang->write('Currency')}} :</label>
                    <select class="form-select currency">
                        <option value="">{{$lang->write('All')}}</option>
                        @foreach ($currencies as $item)
                            <option value="{{$item['code']}}">{{$item['text']}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-25 text-start  mx-2">
                    <label for="">{{$lang->write('Transaction')}} :</label>
                    <select class="form-select type">
                        <option value="deposit">{{$lang->write('Deposit for clients')}}</option>
                        <option value="withdraw">{{$lang->write('Withdraw for clients')}}</option>
                        <option value="transfer">{{$lang->write('Currency convert for clients')}}</option>
                        <option value="branch_deposit">{{$lang->write('Deposit for branches')}}</option>
                        <option value="branch_comission">{{$lang->write('Commission Treasury')}}</option>
                        <option value="expenses_branch">{{$lang->write('Expenses')}}</option>
                        <option value="transfer_branch">{{$lang->write('Currency convert')}}</option>
                        <option value="container_sea_withdraw">{{$lang->write('Container withdrawal fees')}}</option>
                        <option value="container_sky_withdraw">{{$lang->write('Trip withdrawal fees')}}</option>
                    </select>
                </div>
                <div class="w-25 text-start branch mx-2">
                    <label for="">{{$lang->write('From date')}} :</label>
                    <input type="date" class="form-control from">
                </div>
                <div class="w-25 text-start branch mx-2">
                    <label for="">{{$lang->write('To date')}} :</label>
                    <input type="date" class="form-control to">
                </div>
            </div>
        </div>
    </div>

    <div class="card p-3">
        <div class="">
            <button class="sys-btn deposit_branch">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M18.192 16.385h-2.5q-.212 0-.356-.144t-.144-.357t.144-.356t.356-.144h2.5v-2.5q0-.212.144-.356t.357-.144t.356.144t.143.356v2.5h2.5q.213 0 .357.144t.143.357t-.143.356t-.357.144h-2.5v2.5q0 .212-.144.356q-.143.143-.356.143t-.356-.143t-.144-.357zM3.423 20q-.69 0-1.153-.462t-.462-1.153V5.615q0-.69.462-1.152T3.423 4h12.77q.69 0 1.152.463t.463 1.153V9.5q0 .213-.144.356t-.357.144t-.356-.144t-.143-.356V7.384h-14v11q0 .27.173.443t.442.173h11.885q.212 0 .356.144t.144.357t-.144.356t-.356.143zM2.808 6.385h14v-.77q0-.269-.174-.442Q16.462 5 16.192 5H3.423q-.27 0-.442.173q-.173.173-.173.443zm0 0V5z" />
                </svg>
                {{$lang->write('Deposit to branch')}}
            </button>
             <button class="sys-btn ms-2 deposit_commission_branch">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="18" height="12" x="3" y="6" rx="2" />
                        <path stroke-linecap="round" d="M6 9h2m8 6h2" />
                        <circle cx="12" cy="12" r="2" />
                    </g>
                </svg>
                {{$lang->write('Add commission')}}
            </button>
            
            <button class="sys-btn ms-2 transfer_branch">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none">
                        <path d="M24 0v24H0V0zM12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035q-.016-.005-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.017-.018m.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01z" />
                        <path fill="currentColor" d="M20 14a1 1 0 0 1 .117 1.993L20 16H6.414l2.293 2.293a1 1 0 0 1-1.32 1.497l-.094-.083l-3.83-3.83c-.665-.664-.239-1.783.663-1.871L4.241 14zm-4.707-9.707a1 1 0 0 1 1.32-.083l.094.083l3.83 3.83c.665.664.239 1.783-.663 1.871l-.115.006H4a1 1 0 0 1-.117-1.993L4 8h13.586l-2.293-2.293a1 1 0 0 1 0-1.414" stroke-width="0.3" stroke="currentColor" />
                    </g>
                </svg>
                {{$lang->write('Currency convert')}}
            </button>
            <button class="sys-btn ms-2 add_expenses">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M11.5 5.985V4.846q0-.213.143-.356T12 4.346t.357.144t.143.356v1.127q.789.073 1.441.509q.653.435 1.057 1.107q.121.177.084.377q-.038.201-.215.323q-.176.12-.377.083t-.323-.214q-.33-.56-.898-.893q-.567-.334-1.219-.334q-.41 0-.797.133t-.714.373q-.189.117-.374.063q-.186-.054-.303-.242t-.074-.374t.233-.303q.315-.254.691-.4t.788-.196M18.985 20.4l-3.823-3.823q-.414.587-1.16.988q-.746.4-1.502.412v1.139q0 .213-.143.356q-.143.144-.357.144t-.357-.144t-.143-.356v-1.189q-1.1-.217-1.8-.864t-1.142-1.694q-.085-.207-.027-.421t.265-.298q.195-.084.395.008q.201.092.292.3q.355.873 1.031 1.458q.677.584 1.686.584q.72 0 1.296-.314q.577-.315.946-.828L3.6 5.016q-.14-.141-.15-.345t.15-.363t.354-.16t.354.16l15.384 15.384q.14.14.15.345t-.15.363t-.353.16t-.354-.16" stroke-width="0.3" stroke="currentColor" />
                </svg>
                {{$lang->write('Add Expenses')}}
            </button>
            @if (in_array(auth()->user()->type,['admin']))
                <button class="sys-btn ms-2 fix_branch">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M21 7.86c0-.43-.056-.849-.161-1.246c-.092-.349-.522-.432-.776-.177L18.34 8.16a1.767 1.767 0 1 1-2.5-2.5l1.723-1.722c.255-.255.172-.685-.177-.777a4.86 4.86 0 0 0-5.828 6.326c.071.2.031.424-.118.573L3.3 18.2c-.4.4-.4 1.049 0 1.448L4.352 20.7c.4.4 1.047.4 1.447 0l8.14-8.14c.15-.15.374-.19.573-.119A4.86 4.86 0 0 0 21 7.86" />
                    </svg>
                    {{$lang->write('Branches transfers')}}
                </button>
            @endif
            <button class="sys-btn ms-2 container_sea_withdraw">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.6">
                        <path d="M2 21.193c.685 1.051 1.571 1.051 2.273 0c2.257-3.452 4.407 2.483 6 .04c2.43-3.664 4.178 2.689 6-.04c2.376-3.635 3.857 2.385 5.727.391" />
                        <path stroke-linejoin="round" d="m3.572 17l-1.497-4.354c-.271-.789.228-1.646.958-1.646h17.825c3.094 0-.864 6-2.861 6M18 11l-2.799-3.499A4 4 0 0 0 12.078 6H8a2 2 0 0 0-2 2v3m4-5V3a1 1 0 0 0-1-1H8" />
                    </g>
                </svg>
                {{$lang->write('Container withdrawal fees')}}
            </button>
            <button class="sys-btn ms-2 container_sky_withdraw">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M10.5 4.5v4.667a.6.6 0 0 1-.282.51l-7.436 4.647a.6.6 0 0 0-.282.508v.9a.6.6 0 0 0 .746.582l6.508-1.628a.6.6 0 0 1 .746.582v2.96a.6.6 0 0 1-.205.451l-2.16 1.89c-.458.402-.097 1.151.502 1.042l3.256-.591a.6.6 0 0 1 .214 0l3.256.591c.599.11.96-.64.502-1.041l-2.16-1.89a.6.6 0 0 1-.205-.452v-2.96a.6.6 0 0 1 .745-.582l6.51 1.628a.6.6 0 0 0 .745-.582v-.9a.6.6 0 0 0-.282-.508l-7.436-4.648a.6.6 0 0 1-.282-.509V4.5a1.5 1.5 0 0 0-3 0" />
                </svg>
                {{$lang->write('Trip withdrawal fees')}}
            </button>
           
            <button class="sys-btn ms-2 print" style="float:{{auth()->user()->lang === 'ar'?'left':'right'}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M8.616 20q-.667 0-1.141-.475T7 18.386V16H5.192q-.666 0-1.14-.475t-.475-1.14v-3.77q0-.85.577-1.424q.577-.576 1.423-.576h12.846q.85 0 1.425.576t.575 1.424v3.77q0 .666-.474 1.14T18.808 16H17v2.385q0 .666-.475 1.14t-1.14.475zm-3.424-5H7q.039-.633.502-1.086q.463-.452 1.114-.452h6.769q.65 0 1.113.453q.463.452.502 1.085h1.808q.269 0 .442-.173t.173-.442v-3.77q0-.424-.287-.712t-.713-.288H5.577q-.425 0-.712.288t-.288.713v3.769q0 .269.173.442t.442.173M16 8.616V6.23q0-.27-.173-.442q-.173-.173-.442-.173h-6.77q-.269 0-.442.173T8 6.23v2.385H7V6.23q0-.666.475-1.141q.474-.475 1.14-.475h6.77q.666 0 1.14.475q.475.475.475 1.14v2.386zm1.616 3.5q.425 0 .712-.288t.288-.712t-.288-.713t-.712-.288t-.713.288t-.287.713t.287.712t.713.288M16 18.384v-3.307q0-.27-.173-.443t-.442-.173h-6.77q-.269 0-.442.173q-.173.174-.173.443v3.308q0 .269.173.442t.443.173h6.769q.269 0 .442-.173t.173-.443M5.192 9.616h-.615h14.846z" />
                </svg>
                {{$lang->write('Print')}}
            </button>

            <div class="dropdown-center d-inline-block" style="float:{{auth()->user()->lang === 'ar'?'left':'right'}}">
                <button class="btn btn-secondary mx-2" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 256 256">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16">
                        <rect width="224" height="224" x="16" y="16" ry="32" />
                        <path d="M 160.00003,192.00003 V 111.99998" />
                        <path d="M 192.00002,192.00003 V 63.999974" />
                        <path d="m 63.999979,192.00003 v -32" />
                        <path d="m 95.99997,128 v 64.00003" />
                        <path d="m 128,144 v 48.00003" />
                    </g>
                </svg>
                </button>
                <div class="dropdown-menu p-3" style="width: 600px">
                    <div class="mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{$lang->write('Currency')}}</th>
                                    <th>{{$lang->write('Positive balances')}}</th>
                                    <th>{{$lang->write('Negative balances')}}</th>
                                    <th>{{$lang->write('Difference')}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($currencies as $item)
                                    @php
                                        switch ($item['code']) {
                                            case 'usd':
                                                $pos = $usd_p;
                                                $neg = $usd_m;
                                                break;
                                            case 'eur':
                                                $pos = $eur_p;
                                                $neg = $eur_m;
                                                break;
                                            case 'cny':
                                                $pos = $cny_p;
                                                $neg = $cny_m;
                                                break;
                                            case 'den':
                                                $pos = $den_p;
                                                $neg = $den_m;
                                                break;
                                        }
                                        // $pos = $usd_p + $eur_p + $cny_p + $den_p;
                                        // $neg = $usd_m + $eur_m + $cny_m + $den_m;
                                        $diff = $pos - abs($neg);
                                    @endphp
                                    <tr>
                                        <td>{{$item['text']}}</td>
                                        <td>{{$dataController->numberFormat($pos)}}</td>
                                        <td>{{$dataController->numberFormat($neg)}}</td>
                                        <td>{{$dataController->numberFormat($diff)}}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-table mt-2" id="printable" style="direction: {{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}}">
        
    </div>
@endsection