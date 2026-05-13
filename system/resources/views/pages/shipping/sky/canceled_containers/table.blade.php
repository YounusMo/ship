@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();
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
            <th>{{$lang->write('Trip name')}}</th>
            <th>{{$lang->write('Trip number')}}</th>
            <th>{{$lang->write('Port of Arrival')}}</th>
            <th>{{$lang->write('Trip size')}}</th>
            <th>{{$lang->write('Type')}}</th>
            <th>{{$lang->write('Canceled by')}}</th>
            <th>{{$lang->write('Canceled at')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php
                $user = DB::table('users')->select(['name'])->where('id',$item->canceled_by)->first();
            @endphp
            <tr>
                <td>{{$item->name}}</td>
                <td>{{$item->number}}</td>
                <td>{{$item->arrival}}</td>
                <td>{{$item->size}}</td>
                <td>
                    @switch($item->type)
                        @case('full')
                            {{$lang->write('Shared')}}
                        @break
                        @case('custom')
                            @if ($item->commission)
                                {{$lang->write('Custom trip with commission')}}
                            @else
                                {{$lang->write('Custom trip')}}
                            @endif
                        @break
                    @endswitch
                </td>
                <td>{{$user->name ?? '-'}}</td>
                <td>{{$item->canceled_date}} {{$item->canceled_time}}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>