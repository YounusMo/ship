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
            <th style="width: 50px !important;max-width: 50px !important;">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="chk_all">
                </div>
            </th>
            <th>{{$lang->write('Client code')}}</th>
            <th>{{$lang->write('Client name')}}</th>
            <th>{{$lang->write('Company name')}}</th>
            <th>{{$lang->write('Shipping from')}}</th>
            <th>{{$lang->write('Type')}}</th>
            <th>{{$lang->write('Category')}}</th>
            <th>{{$lang->write('Weight')}}</th>
            <th>{{$lang->write('CBM')}}</th>
            <th>{{$lang->write('Number')}}</th>
            <th>{{$lang->write('Price')}}</th>
            <th>{{$lang->write('Additional cost')}}</th>
            <th>{{$lang->write('Currency')}}</th>
            <th>{{$lang->write('Receipt')}}</th>
            <th>{{$lang->write('Brand')}}</th>
            <th>{{$lang->write('Created at')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php
                $data = DB::table('store_sea')->where('id',$item->in_id)->first();
                $cur  = $dataController->get_cur($item->currency , 'text');
            @endphp
            @if ($data)
                <tr>
                    <td style="width: 50px !important;max-width: 50px !important;">
                        <div class="form-check">
                            <input class="form-check-input chk_item" type="checkbox" value="{{$item->id}}" id="chk_{{ $item->id }}">
                        </div>
                    </td>
                    <td class="{{$clients[$item->client_id]->deleted ?? 'deleted'}}">{{$clients[$item->client_id]->code ?? '-'}}</td>
                    <td class="{{$clients[$item->client_id]->deleted ?? 'deleted'}}">{{$clients[$item->client_id]->name ?? '-'}}</td>
                    <td>{{$data->company_name}}</td>
                    <td>{{$lang->write(ucfirst($data->ship_from))}}</td>
                    <td>{{$lang->write(ucfirst($data->type))}}</td>
                    <td>{{$data->category}}</td>
                    <td>{{$item->kg}} {{$lang->write('KG')}}</td>
                    <td>{{$item->cbm}}</td>
                    <td>{{$item->number}}</td>
                    <td>{{$dataController->numberFormat($item->price)}}</td>
                    <td>{{$dataController->numberFormat($item->plus)}}</td>
                    <td>{{$cur}}</td>
                    <td>{{$lang->write(ucfirst($data->receipt))}}</td>
                    <td>{{$lang->write(ucfirst($data->brand))}}</td>
                    <td>{{$item->created_date}} {{$item->created_time}}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>