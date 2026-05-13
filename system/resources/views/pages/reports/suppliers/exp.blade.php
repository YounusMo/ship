@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

@endphp

<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{$lang->write('Amount')}}</th>
            <th>{{$lang->write('Container / Trip number')}}</th>
            <th>{{$lang->write('Notes')}}</th>
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
                <td>{{$item->container_number}}</td>
                <td>{{$item->notes}}</td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>{{$users[$item->created_by] ?? '-'}}</td>
            </tr>
        @endforeach
    </tbody>
</table>