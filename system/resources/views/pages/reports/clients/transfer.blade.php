@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

@endphp
{{-- <div class="d-flex align-items-center mb-3">
    <label for="">{{$lang->write('Currency')}} :</label>
    <select class="form-select depo_currency w-25 mx-2">
        <option value="">{{$lang->write('All')}}</option>
        @foreach ($currencies as $item)
            <option {{$currency === $item['code'] ? 'selected' : ''}} value="{{$item['code']}}">{{$item['text']}}</option>
        @endforeach
    </select>
</div> --}}
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{$lang->write('From currency')}}</th>
            <th>{{$lang->write('To currency')}}</th>
            <th>{{$lang->write('Exchange rate')}}</th>
            <th>{{$lang->write('Purpose')}}</th>
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
                $cur2 = $dataController->get_cur($item->to_currency , 'symbol');

                $data = json_decode($item->data);
            @endphp
            <tr>
                <td>{{$item->auto_id}}</td>
                <td>{{$dataController->numberFormat($item->value)}} {{$cur}}</td>
                <td>{{$dataController->numberFormat($item->transfer_value)}} {{$cur2}}</td>
                <td>{{$dataController->numberFormat($item->exchange_rate)}}</td>
                <td>
                    @if (!empty($item->purpose))
                        <span class="badge bg-secondary">{{ $dataController->purposeLabel($item->purpose) }}</span>
                    @endif
                </td>
                <td><span title="{{$item->notes}}">{{strlen($item->notes) > 20 ? substr($item->notes, 0, 20).'...' : $item->notes}}</span></td>
                <td>
                    {{$dataController->numberFormat($data->from)}} {{$cur}} / {{$dataController->numberFormat($data->to)}} {{$cur2}} 
                </td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>{{$users[$item->created_by] ?? '-'}}</td>
                <td>
                    @if(auth()->user()->id == 2)
                        <button class="btn btn-sm btn-danger" onclick='del_transcation({{$item->id}},"transfer")'>{{$lang->write('Delete')}}</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>