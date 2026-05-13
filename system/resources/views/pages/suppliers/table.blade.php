@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();
    $currencies = $dataController->currencies;
@endphp
@if (count($get) < 1)
    <img src="{{asset('images/empty.png')}}" class="d-block mx-auto" style="width: 500px ; opacity:.5">
    @php
        return
    @endphp    
@endif
<input type="hidden" class="count" value="{{$count}}">
<table class="table">
    <thead>
        <tr>
            <th style="width: 50px !important;max-width: 50px !important;">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="chk_all">
                </div>
            </th>
            <th>{{$lang->write('Name')}}</th>
            <th>{{$lang->write('Sky / Sea')}}</th>
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
                <td>{{$item->name}}</td>
                <td>{{$lang->write(ucfirst($item->sky_sea))}}</td>
                @foreach ($currencies as $cur)
                    <td>{{  $dataController->numberFormat($item->{'balance_' . $cur['code']}) .' '. $cur['symbol']  }}</td>
                @endforeach
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                @if ($item->deleted === 'false')
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="edit({{$item->id}})">{{$lang->write('Edit')}}</button>
                        {{-- <a class="btn btn-primary btn-sm" href="{{url('clients/reports/deposit_print')}}/{{$item->id}}">{{$lang->write('Print')}}</a> --}}

                        <div class="btn-group btn-sm mx-1">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="show_reports({{$item->id}})">
                                {{$lang->write('Reports')}}
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                {{-- <span class="visually-hidden">Toggle Dropdown</span> --}}
                            </button>
                            <ul class="dropdown-menu">
                                <li onclick="show_deposit({{$item->id}},'{{$dataController->transaction_number('deposit',$item->id)}}')">
                                    <a class="dropdown-item" href="#">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M18.192 16.385h-2.5q-.212 0-.356-.144t-.144-.357t.144-.356t.356-.144h2.5v-2.5q0-.212.144-.356t.357-.144t.356.144t.143.356v2.5h2.5q.213 0 .357.144t.143.357t-.143.356t-.357.144h-2.5v2.5q0 .212-.144.356q-.143.143-.356.143t-.356-.143t-.144-.357zM3.423 20q-.69 0-1.153-.462t-.462-1.153V5.615q0-.69.462-1.152T3.423 4h12.77q.69 0 1.152.463t.463 1.153V9.5q0 .213-.144.356t-.357.144t-.356-.144t-.143-.356V7.384h-14v11q0 .27.173.443t.442.173h11.885q.212 0 .356.144t.144.357t-.144.356t-.356.143zM2.808 6.385h14v-.77q0-.269-.174-.442Q16.462 5 16.192 5H3.423q-.27 0-.442.173q-.173.173-.173.443zm0 0V5z" />
                                        </svg>
                                        {{$lang->write('Deposit')}}
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