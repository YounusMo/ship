@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

  
@endphp


@if ($type === 'transfer')
        
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>{{$lang->write('Amount')}}</th>
                <th>{{$lang->write('Exchange rate')}}</th>
                <th>{{$lang->write('From currency')}}</th>
                <th>{{$lang->write('To currency')}}</th>
                <th>{{$lang->write('Result')}}</th>
                <th>{{$lang->write('Notes')}}</th>
                <th>{{$lang->write('Created at')}}</th>
                <th>{{$lang->write('Created by')}}</th>
                <th>{{$lang->write('Action')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($get as $item)
                @php
                    $cur = $dataController->get_cur($item->currency , 'symbol');
                @endphp
                <tr class="tr_" data-id="{{$item->id}}">
                    <input type="hidden" class="result_" value="{{$item->transfer_value}}" data-id='{{$item->id}}'>
                    <td>{{$item->auto_id}}</td>
                    <td style="width:150px"><input type="number" style="width:100px" value="{{$item->value}}" class="form-control val_"  data-id='{{$item->id}}'></td>
                    <td style="width:170px;justify-content:space-between" class="d-flex align-items-center">
                        <input type="number" style="width:80%" value="{{$item->exchange_rate}}" class="form-control exchange_"  data-id='{{$item->id}}'>
                        <button class="btn btn-sm btn-secondary mx-1" title="{{$lang->write('Switch')}}" onclick="switchCurPending({{$item->id}})">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-linecap="square" stroke-width="2" d="M21.448 13c-.5 4.777-4.539 8.5-9.448 8.5A9.5 9.5 0 0 1 3.38 16m-.88 4.5v-5h3M2.552 11C3.052 6.223 7.09 2.5 12 2.5A9.5 9.5 0 0 1 20.62 8m.88-4.5v5h-3" />
                            </svg>
                        </button>
                    </td>
                    <td>
                        <select data-id="{{$item->id}}" class="form-select from_currency_">
                            @foreach ($currencies as $currency)
                                <option {{$item->currency === $currency['code'] ? 'selected':''}} value="{{$currency['code']}}">{{$currency['text']}}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select data-id="{{$item->id}}" class="form-select to_currency_">
                            @foreach ($currencies as $currency)
                                <option {{$item->to_currency === $currency['code'] ? 'selected':''}} value="{{$currency['code']}}">{{$currency['text']}}</option>
                            @endforeach
                        </select>
                    </td>
                    <td style="white-space:nowrap !important" class="result_transfer">{{$item->transfer_value}} {{$dataController->get_cur($item->to_currency , 'symbol')}}</td>
                    <td style="white-space:unset !important">{{$item->notes}}</td>
                    <td>{{$item->created_date}} {{$item->created_time}}</td>
                    <td>{{$users[$item->created_by] ?? '-'}}</td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="approveReject({{$item->id}},'approved','{{$type}}')">{{$lang->write('Approve')}}</button>
                        <button class="btn btn-sm btn-danger" onclick="approveReject({{$item->id}},'rejected','{{$type}}')">{{$lang->write('Reject')}}</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>{{$lang->write('Amount')}}</th>
                <th>{{$lang->write('Currency')}}</th>
                <th>{{$lang->write('Notes')}}</th>
                <th>{{$lang->write('Created at')}}</th>
                <th>{{$lang->write('Created by')}}</th>
                <th>{{$lang->write('Action')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($get as $item)
                @php
                    $cur = $dataController->get_cur($item->currency , 'symbol');
                @endphp
                <tr>
                    <td>{{$item->auto_id}}</td>
                    <td style="width:150px"><input type="number" style="width:100px" value="{{$item->value}}" class="form-control val_"  data-id='{{$item->id}}'></td>
                    <td>{{$dataController->get_cur($item->currency , 'text')}}</td>
                    <td style="white-space:unset !important">{{$item->notes}}</td>
                    <td>{{$item->created_date}} {{$item->created_time}}</td>
                    <td>{{$users[$item->created_by] ?? '-'}}</td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="approveReject({{$item->id}},'approved','{{$type}}')">{{$lang->write('Approve')}}</button>
                        <button class="btn btn-sm btn-danger" onclick="approveReject({{$item->id}},'rejected','{{$type}}')">{{$lang->write('Reject')}}</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
