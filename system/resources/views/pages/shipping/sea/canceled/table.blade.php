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
            <th>{{$lang->write('Client code')}}</th>
            <th>{{$lang->write('Client name')}}</th>
            <th>{{$lang->write('Company name')}}</th>
            <th>{{$lang->write('Shipping from')}}</th>
            <th>{{$lang->write('Type')}}</th>
            <th>{{$lang->write('Category')}}</th>
            <th>{{$lang->write('Weight')}}</th>
            <th>{{$lang->write('CBM')}}</th>
            <th>{{$lang->write('Number')}}</th>
            <th>{{$lang->write('Receipt')}}</th>
            <th>{{$lang->write('Brand')}}</th>
            <th>{{$lang->write('Notes')}}</th>
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
                <td class="{{$clients[$item->client_id]->deleted ?? 'deleted'}}">{{$clients[$item->client_id]->code ?? '-'}}</td>
                <td class="{{$clients[$item->client_id]->deleted ?? 'deleted'}}">{{$clients[$item->client_id]->name ?? '-'}}</td>
                <td>{{$item->company_name}}</td>
                <td>{{$item->ship_from}}</td>
                <td>{{$lang->write(ucfirst($item->type))}}</td>
                <td>{{$item->category}}</td>
                <td>{{$item->kg}} {{$lang->write('KG')}}</td>
                <td>{{$item->cbm}}</td>
                <td>{{$item->number}}</td>
                <td>{{$lang->write(ucfirst($item->receipt))}}</td>
                <td>{{$lang->write(ucfirst($item->brand))}}</td>
                <td>{{$item->notes}}</td>
                <td>{{$user->name ?? '-'}}</td>
                <td>{{$item->canceled_date}} {{$item->canceled_time}}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>