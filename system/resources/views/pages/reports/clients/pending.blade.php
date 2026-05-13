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
                    <td style="width:150px">{{$item->value}}</td>
                   
                    <td>
                        {{$dataController->get_cur($item->currency , 'text')}}
                    </td>
                    <td>
                        {{$dataController->get_cur($item->to_currency , 'text')}}
                    </td>
                    <td style="white-space:nowrap !important" class="result_transfer">{{$item->transfer_value}} {{$dataController->get_cur($item->to_currency , 'symbol')}}</td>
                    <td style="white-space:unset !important"><span title="{{$item->notes}}">{{strlen($item->notes) > 20 ? substr($item->notes, 0, 20).'...' : $item->notes}}</span></td>
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
