@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

@endphp
 <style>
        body {
            font-family: 'amiri';
            direction: rtl;
            text-align: right;
        }
        table{
            width: 100%;
        }

        th{
            background: #dddddd;
            white-space: nowrap;
            padding: 5px
        }
        td{
            white-space: nowrap;
            border: 1px solid black;
            padding: 5px
        }
    </style>
<table class="table" >
    <thead>
        <tr>
            <th>#</th>
            <th>{{$lang->write('Amount')}}</th>
            <th>{{$lang->write('Commission')}}</th>
            <th>{{$lang->write('Currency')}}</th>
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
            @endphp
            <tr>
                <td>{{$item->auto_id}}</td>
                <td>{{$dataController->numberFormat($item->value)}} {{$cur}}</td>
                <td>{{$dataController->numberFormat($item->commission)}} {{$cur}}</td>
                <td>{{$dataController->get_cur($item->currency , 'text')}}</td>
                <td>{{$item->notes}}</td>
                <td>{{$dataController->numberFormat($item->remaining_balance)}} {{$cur}}</td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>{{$users[$item->created_by] ?? '-'}}</td>
            </tr>
        @endforeach
    </tbody>
</table>