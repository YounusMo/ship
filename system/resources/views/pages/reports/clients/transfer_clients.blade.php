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
    <select class="form-select transfer_client_currency w-25 mx-2">
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
            <th>{{$lang->write('Type')}}</th>
            <th>{{$lang->write('Amount')}}</th>
            <th>{{$lang->write('From client')}}</th>
            <th>{{$lang->write('To client')}}</th>
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
                $cur  = $dataController->get_cur($item->currency , 'symbol');

                $data = json_decode($item->data);

                if(!isset($data->from_client) || !isset($data->to_client)){
                    continue;
                }

                $from_client = DB::table('clients')->where('id',$data->from_client)->first();
                $to_client = DB::table('clients')->where('id',$data->to_client)->first();
               
            @endphp
            <tr>
                <td>{{$item->auto_id}}</td>
                <td>{{$lang->write(ucfirst($item->type))}}</td>
                <td class="{{$item->type === 'deposit' ? 'text-success' : 'text-danger'}}">{{$dataController->numberFormat($item->value)}} {{$cur}}</td>
                <td>{{$from_client->name ?? '-'}}</td>
                <td>{{$to_client->name ?? '-'}}</td>
                <td><span title="{{$item->notes}}">{{strlen($item->notes) > 20 ? substr($item->notes, 0, 20).'...' : $item->notes}}</span></td>
                <td>
                    {{$dataController->numberFormat($item->remaining_balance)}} {{$cur}}
                </td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>{{$users[$item->created_by] ?? '-'}}</td>
                <td>
                    @if(auth()->user()->id == 2)
                        <button class="btn btn-sm btn-danger" onclick='del_transcation({{$item->id}},"transfer_client")'>{{$lang->write('Delete')}}</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>