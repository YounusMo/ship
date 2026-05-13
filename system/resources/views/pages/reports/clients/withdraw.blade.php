@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

@endphp
<div class="d-flex align-items-center mb-3">
    <label for="">{{$lang->write('Currency')}} :</label>
    <select class="form-select with_currency w-25 mx-2">
        <option value="">{{$lang->write('All')}}</option>
        @foreach ($currencies as $item)
            <option {{$currency === $item['code'] ? 'selected' : ''}} value="{{$item['code']}}">{{$item['text']}}</option>
        @endforeach
    </select>
</div>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{$lang->write('Amount')}}</th>
            <th>{{$lang->write('Currency')}}</th>
            <th>{{$lang->write('Notes')}}</th>
            <th>{{$lang->write('Remaining balance')}}</th>
            <th>{{$lang->write('Created at')}}</th>
            <th>{{$lang->write('Created by')}}</th>
            <th>{{$lang->write('Action')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php
                $cur = $dataController->get_cur($item->currency , 'symbol');
                $data = json_decode($item->data);
            @endphp
            <tr>
                <td>{{$item->auto_id}}</td>
                <td>{{$dataController->numberFormat($item->value)}} {{$cur}}</td>
                <td>{{$dataController->get_cur($item->currency , 'text')}}</td>
                <td>
                    @if (isset($data->from_client) && isset($data->to_client))
                        @if ($data->to_client == $item->client_id)
                            <span>{{$lang->write('Transfer from client')}} {{ $dataController->get_client($data->from_client,'code') }}</span>
                        @else
                            <span class="px-1">{{$lang->write('Transfer to client')}} {{ $dataController->get_client($data->to_client,'code') }}</span>
                        @endif
                        @if (strlen($item->notes) > 0)
                            <span class="mx-1"> / <span title="{{$item->notes}}">{{strlen($item->notes) > 20 ? substr($item->notes, 0, 20).'...' : $item->notes}}</span></span>
                        @endif
                    @else
                        <span title="{{$item->notes}}">{{strlen($item->notes) > 20 ? substr($item->notes, 0, 20).'...' : $item->notes}}</span>
                    @endif
                </td>
                <td>{{$dataController->numberFormat($item->remaining_balance)}} {{$cur}}</td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>{{$users[$item->created_by] ?? '-'}}</td>
                <td>
                    @if(auth()->user()->id == 2)
                        <button class="btn  btn-sm btn-danger" onclick='del_transcation({{$item->id}},"withdraw")'>{{$lang->write('Delete')}}</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>