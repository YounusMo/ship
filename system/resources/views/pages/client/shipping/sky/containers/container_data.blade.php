@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $currencies = $dataController->shipping_currencies;
    
    $sea_purpose = $dataController->sea_purpose;

    $get   = DB::table('containers_sky')->where('id',$id)->first();
    $data_ = DB::table('store_out_sky')->where('client_id',Auth::guard('client')->user()->id)->where('container_id',$id)->get();

    $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
        return DB::table('clients')
            ->where('deleted', 'false')
            ->select('id', 'name', 'code')
            ->get()
            ->keyBy('id');
    });

    

    $fees = json_decode($get->fees,true);

    $fees_notes  = [];
    $fees_values = [];

 
    if(!$get){
        abort(404);
    }
@endphp
@extends('layout')
@section('content')


<div style="width:100%; overflow:scroll" class="container_data">
    <input type="hidden" class="container_id" value="{{$id}}">
    <table class="table">
        <thead>
            <tr>
                <th>{{$lang->write('Trip name')}}</th>
                <th>{{$lang->write('Trip number')}}</th>
                <th>{{$lang->write('Port of Arrival')}}</th>
                {{-- <th>{{$lang->write('Packaging type')}}</th>
                <th>{{$lang->write('Trip size')}}</th> --}}
                {{-- <th>{{$lang->write('Supplier')}}</th> --}}
                <th>{{$lang->write('Created at')}}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{$get->name}}</td>
                <td>{{$get->number}}</td>
                <td>{{$get->arrival}}</td>
                {{-- <td>{{$lang->write(ucfirst($get->packaging_type))}}</td>
                <td>{{$get->size}}</td> --}}
                {{-- <td>{{$supplier->name ?? '-'}}</td> --}}
                <td>{{$get->created_date}} {{$get->created_time}}</td>
            </tr>
        </tbody>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>{{$lang->write('Client code')}}</th>
                <th>{{$lang->write('Client name')}}</th>
                <th>{{$lang->write('Company name')}}</th>
                <th>{{$lang->write('Shipping from')}}</th>
                <th>{{$lang->write('Type')}}</th>
                <th>{{$lang->write('Category')}}</th>
                <th>{{$lang->write('Unit')}}</th>
                <th>{{$lang->write('Total cost')}}</th>
                <th>{{$lang->write('Receipt')}}</th>
                <th>{{$lang->write('Brand')}}</th>
                <th>{{$lang->write('Notes')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data_ as $item)
                @php
                    $data = DB::table('store_sky')->where('id',$item->in_id)->first();

                    $total = 0;

                    if($item->unit === 'cbm'){
                        $total = floatval($item->price * $item->cbm);
                    }

                    if($item->unit === 'kg'){
                        $total = floatval($item->price * $item->kg);
                    }

                    if($item->plus > 0){
                        $total += floatval($item->plus);
                    }

                    $disabled = false;

                @endphp
                @if ($data)
                    <tr data-id="{{$item->id}}" class="tr_item" data-disabled='{{$disabled ? 'true' : 'false'}}'>
                    
                        <td>{{$clients[$item->client_id]->code ?? '-'}}</td>
                        <td>{{$clients[$item->client_id]->name ?? '-'}}</td>
                        <td>{{$data->company_name}}</td>
                        <td>{{$lang->write(ucfirst($data->ship_from))}}</td>
                        <td>{{$lang->write(ucfirst($data->type))}}</td>
                        <td>{{$data->category}}</td>
                        <td>{{$lang->write(ucfirst($item->unit))}}</td>
                        <td><span class="total" data-id='{{$item->id}}'>{{$dataController->numberFormat($total)}}</span> <span data-id='{{$item->id}}' class="cur">{{$dataController->get_cur($item->currency , 'symbol')}}</span></td>
                        
                        <td>{{$lang->write(ucfirst($data->receipt))}}</td>
                        <td>{{$lang->write(ucfirst($data->brand))}}</td>
                        <td>{{strlen($data->notes) > 0 ? $data->notes : '-'}}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

</div>

@endsection