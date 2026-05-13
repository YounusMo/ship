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
            <th>{{$lang->write('Created at')}}</th>
            <th>{{$lang->write('Action')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            <tr>
                <td>{{$clients[$item->client_id]->code ?? '-'}}</td>
                <td>{{$clients[$item->client_id]->name ?? '-'}}</td>
                <td>{{$item->company_name}}</td>
                <td>{{$lang->write(ucfirst($item->ship_from))}}</td>
                <td>{{$lang->write(ucfirst($item->type))}}</td>
                <td>{{$item->category}}</td>
                <td>{{$item->kg}} {{$lang->write('KG')}}</td>
                <td>{{$item->cbm}}</td>
                <td>{{$item->number}}</td>
                <td>{{$lang->write(ucfirst($item->receipt))}}</td>
                <td>{{$lang->write(ucfirst($item->brand))}}</td>
                <td>{{$item->notes}}</td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>
                    <button class="btn btn-success btn-sm" onclick="showEject({{$item->id}})">{{$lang->write('Eject')}}</button>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>