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
    <select class="form-select exp_currency w-25 mx-2">
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
            <th>{{$lang->write('Input')}}</th>
            <th>{{$lang->write('Output')}}</th>
            <th>{{$lang->write('Currency')}}</th>
            <th>{{$lang->write('Container / Trip number')}}</th>
            <th>{{$lang->write('Notes')}}</th>
            <th>{{$lang->write('Remaining balance')}}</th>
            <th>{{$lang->write('Created at')}}</th>
            <th>{{$lang->write('Created by')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php
                $cur = $dataController->get_cur($item->currency , 'symbol');
                $data= json_decode($item->data);
            @endphp
            <tr>
                <td>{{$item->auto_id}}</td>
                <td>
                    @if ($item->plus_minus === 'plus')
                        {{$dataController->numberFormat($item->value)}} {{$cur}}
                    @else 
                        -
                    @endif
                </td>
                <td>
                    @if ($item->plus_minus === 'minus')
                        {{$dataController->numberFormat($item->value)}} {{$cur}}
                    @else 
                        -
                    @endif
                </td>
                <td>{{$dataController->get_cur($item->currency , 'text')}}</td>
                <td>{{$data->container_number}}</td>
                <td><span title="{{$item->notes}}">{{strlen($item->notes) > 20 ? substr($item->notes, 0, 20).'...' : $item->notes}}</span></td>
                <td>{{$dataController->numberFormat($item->remaining_balance)}} {{$cur}}</td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>{{$users[$item->created_by] ?? '-'}}</td>
            </tr>
        @endforeach
    </tbody>
</table>