@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();
    $currencies = $dataController->currencies;

@endphp

<input type="hidden" class="count" value="{{$count}}">
@if (count($get) < 1)
    <img src="{{asset('images/empty.png')}}" class="d-block mx-auto" style="width: 500px ; opacity:.5">
    @php
        return
    @endphp    
@endif
<table class="table">
    <thead>
        <tr>
            <th style="width: 50px !important;max-width: 50px !important;">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="chk_all">
                </div>
            </th>
            <th>{{$lang->write('Code')}}</th>
            <th>{{$lang->write('Name')}}</th>
            <th>{{$lang->write('Email')}}</th>
            <th>{{$lang->write('Phone')}}</th>
            <th>{{$lang->write('Type')}}</th>
            <th>{{$lang->write('Branch')}}</th>
            @foreach ($currencies as $item)
                <th>{{$lang->write('Balance')}} {{$item['text']}}</th>
            @endforeach
            <th>{{$lang->write('Created at')}}</th>
            @if ($show_deleted === 'false')
                <th>{{$lang->write('Action')}}</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            <tr>
                <td style="width: 50px !important;max-width: 50px !important;">
                    <div class="form-check">
                        <input class="form-check-input chk_item" type="checkbox" value="{{$item->id}}" id="chk_{{ $item->id }}">
                    </div>
                </td>
                <td>{{$item->code}}</td>
                <td>{{$item->name}}</td>
                <td>{{$item->email}}</td>
                <td>{{$item->phone}}</td>
                <td>{{$lang->write(ucfirst($item->type))}}</td>
                <td>{{$lang->branch($item->branch)}}</td>
                @foreach ($currencies as $cur)
                    <td>{{  $dataController->numberFormat($item->{'balance_' . $cur['code']}) .' '. $cur['symbol']  }}</td>
                @endforeach
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                @if ($item->deleted === 'false')
                    <td>
                        @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                            <button class="btn btn-primary btn-sm" onclick="edit({{$item->id}})">{{$lang->write('Edit')}}</button>
                        @endif

                        @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                            @php
                                $proforma_count = DB::table('sourcing_requests')
                                    ->where('client_id', $item->id)
                                    ->whereNull('deleted_at')
                                    ->count();
                            @endphp
                            <a class="btn btn-info btn-sm" href="{{ url('/sourcing?client_id=' . $item->id) }}" title="{{ $lang->write('Open sourcing requests / proformas for this client') }}">
                                {{ $lang->write('Proformas') }}
                                @if ($proforma_count > 0)
                                    <span class="badge bg-light text-dark">{{ $proforma_count }}</span>
                                @endif
                            </a>
                        @endif

                        @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                            @php
                                $chk_pending = DB::table('clients_transactions')->where('status','pending')->where('client_id',$item->id)->count();
                            @endphp
                            @if ($chk_pending > 0)
                                <button class="btn btn-warning btn-sm" onclick="peningTransaction({{$item->id}})">{{$lang->write('Pending transactions')}}</button>
                            @endif
                        @endif
                        {{-- <a class="btn btn-primary btn-sm" href="{{url('clients/reports/deposit_print')}}/{{$item->id}}">{{$lang->write('Print')}}</a> --}}

                        <div class="btn-group btn-sm mx-1">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="show_reports({{$item->id}})">
                                {{$lang->write('Reports')}}
                            </button>

                            <button type="button" class="btn btn-secondary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                {{-- <span class="visually-hidden">Toggle Dropdown</span> --}}
                            </button>
                            <ul class="dropdown-menu">
                                @if (in_array(auth()->user()->type , ['admin','branch_admin']))
                                    <li onclick="show_deposit({{$item->id}},'{{$dataController->transaction_number('deposit',$item->id)}}')">
                                        <a class="dropdown-item" href="#">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                                <path fill="currentColor" d="M18.192 16.385h-2.5q-.212 0-.356-.144t-.144-.357t.144-.356t.356-.144h2.5v-2.5q0-.212.144-.356t.357-.144t.356.144t.143.356v2.5h2.5q.213 0 .357.144t.143.357t-.143.356t-.357.144h-2.5v2.5q0 .212-.144.356q-.143.143-.356.143t-.356-.143t-.144-.357zM3.423 20q-.69 0-1.153-.462t-.462-1.153V5.615q0-.69.462-1.152T3.423 4h12.77q.69 0 1.152.463t.463 1.153V9.5q0 .213-.144.356t-.357.144t-.356-.144t-.143-.356V7.384h-14v11q0 .27.173.443t.442.173h11.885q.212 0 .356.144t.144.357t-.144.356t-.356.143zM2.808 6.385h14v-.77q0-.269-.174-.442Q16.462 5 16.192 5H3.423q-.27 0-.442.173q-.173.173-.173.443zm0 0V5z" />
                                            </svg>
                                            {{$lang->write('Deposit')}}
                                        </a>
                                    </li>
                                    <li onclick="show_withdraw({{$item->id}},'{{$dataController->transaction_number('withdraw',$item->id)}}')">
                                        <a class="dropdown-item" href="#">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                                <path fill="currentColor" d="M3 4.27L4.28 3L21 19.72L19.73 21l-3.67-3.67c-.62.67-1.52 1.22-2.56 1.49V21h-3v-2.18C8.47 18.31 7 16.79 7 15h2c0 1.08 1.37 2 3 2c1.13 0 2.14-.44 2.65-1.08l-2.97-2.97C9.58 12.42 7 11.75 7 9c0-.23 0-.45.07-.66zm7.5.91V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2c-.37 0-.72.05-1.05.13L9.4 5.58z" />
                                            </svg>
                                            {{$lang->write('Withdraw')}}
                                        </a>
                                    </li>
                                    <li onclick="show_withdraw_commission({{$item->id}},'{{$dataController->transaction_number('withdraw_commission',$item->id)}}')">
                                        <a class="dropdown-item" href="#">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                                <path fill="currentColor" d="M3 4.27L4.28 3L21 19.72L19.73 21l-3.67-3.67c-.62.67-1.52 1.22-2.56 1.49V21h-3v-2.18C8.47 18.31 7 16.79 7 15h2c0 1.08 1.37 2 3 2c1.13 0 2.14-.44 2.65-1.08l-2.97-2.97C9.58 12.42 7 11.75 7 9c0-.23 0-.45.07-.66zm7.5.91V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2c-.37 0-.72.05-1.05.13L9.4 5.58z" />
                                            </svg>
                                            {{$lang->write('Withdraw commission')}}
                                        </a>
                                    </li>
                                    <li onclick="show_transfer({{$item->id}},'{{$dataController->transaction_number('transfer',$item->id)}}')">
                                        <a class="dropdown-item" href="#">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                                                <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4">
                                                    <path d="m19 16l5 6l5-6" />
                                                    <path d="M9 14s7.5-11.5 20.5-7S42 24.5 42 24.5M39 34s-6 11-19.5 7.5S6 24 6 24M42 8v16M6 24v16m12-12h12m-12-6h12m-6 0v12" />
                                                </g>
                                            </svg>
                                            {{$lang->write('Change currency')}}
                                        </a>
                                    </li>
                                    <li onclick="show_transfer_clients({{$item->id}},'{{$dataController->transaction_number('transfer_clients',$item->id)}}')">
                                        <a class="dropdown-item" href="#">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                                <g fill="none">
                                                    <path d="M24 0v24H0V0zM12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035q-.016-.005-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.017-.018m.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01z" />
                                                    <path fill="currentColor" d="M20 14a1 1 0 0 1 .117 1.993L20 16H6.414l2.293 2.293a1 1 0 0 1-1.32 1.497l-.094-.083l-3.83-3.83c-.665-.664-.239-1.783.663-1.871L4.241 14zm-4.707-9.707a1 1 0 0 1 1.32-.083l.094.083l3.83 3.83c.665.664.239 1.783-.663 1.871l-.115.006H4a1 1 0 0 1-.117-1.993L4 8h13.586l-2.293-2.293a1 1 0 0 1 0-1.414" stroke-width="0.3" stroke="currentColor" />
                                                </g>
                                            </svg>
                                            {{$lang->write('Transfer')}}
                                        </a>
                                    </li>
                                @endif
                                
                                <li onclick="show_all_reports({{$item->id}})">
                                    <a class="dropdown-item" href="#">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M18 12c-.55 0-1 .45-1 1v5.22c0 .55-.45 1-1 1H6c-.55 0-1-.45-1-1V8c0-.55.45-1 1-1h5c.55 0 1-.45 1-1s-.45-1-1-1H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-6c0-.55-.45-1-1-1m3.02-7H19V2.98c0-.54-.44-.98-.98-.98h-.03c-.55 0-.99.44-.99.98V5h-2.01c-.54 0-.98.44-.99.98v.03c0 .55.44.99.99.99H17v2.01c0 .54.44.99.99.98h.03c.54 0 .98-.44.98-.98V7h2.02c.54 0 .98-.44.98-.98v-.04c0-.54-.44-.98-.98-.98" />
                                            <path fill="currentColor" d="M14 9H8c-.55 0-1 .45-1 1s.45 1 1 1h6c.55 0 1-.45 1-1s-.45-1-1-1m0 3H8c-.55 0-1 .45-1 1s.45 1 1 1h6c.55 0 1-.45 1-1s-.45-1-1-1m0 3H8c-.55 0-1 .45-1 1s.45 1 1 1h6c.55 0 1-.45 1-1s-.45-1-1-1" />
                                        </svg>
                                        {{$lang->write('Create a report')}}
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ url('/clients/reports/statement/'.$item->id) }}" target="_blank">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                        {{$lang->write('Statement of account')}}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>