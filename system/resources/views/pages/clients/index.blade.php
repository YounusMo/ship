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
    
    <div class="row">
        <div class="col-lg-2 col-12 mb-2">
            <div class="d-flex align-items-center">
                <h4 class="h4">{{$lang->write('Clients')}}</h4>
                <span class="table_counter">0</span>
            </div>
        </div>
        <div class="col-lg-10 col-12 mb-2 text-end">
            <div class="d-flex align-items-center justify-content-end">
                <div class="input-group w-50 mx-2">
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
                <div class="dropdown-center">
                    <button class="btn btn-secondary mx-2" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M8.857 12.506C6.37 10.646 4.596 8.6 3.627 7.45c-.3-.356-.398-.617-.457-1.076c-.202-1.572-.303-2.358.158-2.866S4.604 3 6.234 3h11.532c1.63 0 2.445 0 2.906.507c.461.508.36 1.294.158 2.866c-.06.459-.158.72-.457 1.076c-.97 1.152-2.747 3.202-5.24 5.065a1.05 1.05 0 0 0-.402.747c-.247 2.731-.475 4.227-.617 4.983c-.229 1.222-1.96 1.957-2.888 2.612c-.552.39-1.222-.074-1.293-.678a196 196 0 0 1-.674-6.917a1.05 1.05 0 0 0-.402-.755" />
                        </svg>
                    </button>
                    <div class="dropdown-menu p-3" style="width: 600px">
                        <h5 class="text-center">{{$lang->write('Quick filters')}}</h5>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            
                            <button class="btn btn-secondary mx-1 get_positive">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14" />
                                </svg>
                                {{$lang->write('Positive balances')}}
                            </button>
                            <button class="btn btn-primary mx-1 get_negative">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                                </svg>
                                {{$lang->write('Negative balances')}}
                            </button>
                            
                            <button class="btn btn-warning get_pending">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 16 16">
                                    <g fill="currentColor">
                                        <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432z" />
                                        <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z" />
                                        <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5" />
                                    </g>
                                </svg>
                                {{$lang->write('Pending transactions')}}
                            </button>
                        </div>
                        <hr>
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
                                            $currencyData = $bl[$item['code']] ?? [0, 0, 0];
                                        @endphp
                                        <tr>
                                            <td>{{$item['text']}}</td>
                                            <td>{{$dataController->numberFormat($currencyData[0])}}</td>
                                            <td>{{$dataController->numberFormat($currencyData[1])}}</td>
                                            <td>{{$dataController->numberFormat($currencyData[2])}}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <span class="in_trash d-none">
                    <button class="btn btn-primary show_trash" data-table='{{$page}}'>{{$lang->write('Back')}}</button>
                    <button class="btn btn-success restore" data-table='{{$page}}'>{{$lang->write('Restore')}}</button>
                    <button class="btn btn-danger delete_permanent" data-table='{{$page}}'>{{$lang->write('Permanent deletion')}}</button>
                </span>
                <span class="out_trash">
                    @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                        <button class="btn btn-secondary mx-1 reset_filters">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12S6.477 2 12 2v2a8 8 0 1 0 4.5 1.385V8h-2V2h6v2H18a9.99 9.99 0 0 1 4 8" />
                            </svg>
                            {{$lang->write('Reset filters')}}
                        </button>
                        <button class="btn btn-primary new">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                <g fill="none">
                                    <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-linecap="round" stroke-width="1.3" />
                                    <path fill="currentColor" fill-rule="evenodd" d="M15.276 16a11 11 0 0 0-4.37-.446c-1.64.162-3.191.686-4.456 1.517c-1.264.832-2.196 1.943-2.648 3.208a.5.5 0 1 0 .941.336C5.11 19.588 5.885 18.64 7 17.907s2.508-1.21 4.005-1.358c.55-.054 1.103-.063 1.649-.028A2 2 0 0 1 14 16z" clip-rule="evenodd" />
                                    <path stroke="currentColor" stroke-linecap="round" d="M18 14v8m4-4h-8" stroke-width="1.3" />
                                </g>
                            </svg>
                            {{$lang->write('New client')}}
                        </button>
                    @endif

                    @if (auth()->user()->type === 'admin')
                        <button type="button" class="btn btn-danger delete" data-table='{{$page}}'>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m18 9l-.84 8.398c-.127 1.273-.19 1.909-.48 2.39a2.5 2.5 0 0 1-1.075.973C15.098 21 14.46 21 13.18 21h-2.36c-1.279 0-1.918 0-2.425-.24a2.5 2.5 0 0 1-1.076-.973c-.288-.48-.352-1.116-.48-2.389L6 9m7.5 6.5v-5m-3 5v-5m-6-4h4.615m0 0l.386-2.672c.112-.486.516-.828.98-.828h3.038c.464 0 .867.342.98.828l.386 2.672m-5.77 0h5.77m0 0H19.5" />
                            </svg>
                            {{$lang->write('Delete')}}
                        </button>
                    @endif
                   
                </span>
                
            </div>
        </div>
    </div>
    <div class="main-table mt-2">
        
    </div>

    
@endsection