@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $currency_exchange_rates = $dataController->currency_exchange_rates;

    $currencies = $dataController->shipping_currencies;
    
    $sea_purpose = $dataController->sea_purpose;

    $get   = DB::table('containers_sea')->where('id',$id)->first();
    $data_ = DB::table('store_out_sea')->where('container_id',$id)->get();

    $clients = DB::table('clients')
            // ->where('deleted', 'false')
            ->select('id', 'name', 'code','branch','deleted')
            ->get()
            ->keyBy('id');

    
    $branches = DB::table('branches');
    $branches = $branches->where('deleted', 'false');
    if (in_array(auth()->user()->type , ['branch_admin'])) {
        $branches = $branches->where('id', auth()->user()->branch);
    }
    $branches = $branches->orderBy('id', 'DESC');
    $branches = $branches->get();
    $branches = $branches->map(function ($branch)use($lang) {
        return [
            'val' => (string) $branch->id,
            'txt' => $lang->branch($branch->id),
        ];
    })
    ->toArray();

    $fees = json_decode($get->fees,true);

    $fees_notes  = [];
    $fees_values = [];
    $fees_curs = [];

    $supplier = DB::table('suppliers')->select(['name','deleted'])->where('id',$get->supplier)->first();

    if(!$get){
        abort(404);
    }

    $total_costs   = 0;
    $total_cbm     = 0;
    $total_kg      = 0;
    $total_numbers = 0;

    $total_expenses = 0;


@endphp
@extends('layout')
@section('content')
@include('pages.shipping.sea.containers.container_data_modal')
@include('pages.shipping.sea.containers.payment')

<div style="width:100%; overflow:scroll" class="container_data">
    <input type="hidden" class="container_id" value="{{$id}}">

    {{-- PIN-protected bulk-print of every sticker in this container.
         The PIN itself is hashed in settings; the form just POSTs and
         opens the returned PDF in a new tab on success. --}}
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#bulkStickerPinModal">
            {{$lang->write('Print all stickers')}}
        </button>
    </div>

    @include('pages.shipping.stickers.bulk_pin_modal', [
        'containerTable' => 'containers_sea',
        'containerId'    => $id,
        'lang'           => $lang,
    ])

    <table class="table">
        <thead>
            <tr>
                <th>{{$lang->write('Container name')}}</th>
                <th>{{$lang->write('Container number')}}</th>
                <th>{{$lang->write('Port of Arrival')}}</th>
                {{-- <th>{{$lang->write('Packaging type')}}</th> --}}
                <th>{{$lang->write('Container size')}}</th>
                <th>{{$lang->write('Shipping Line')}}</th>
                <th>{{$lang->write('Created at')}}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{$get->name}}</td>
                <td>{{$get->number}}</td>
                <td>{{$get->arrival}}</td>
                {{-- <td>{{$lang->write(ucfirst($get->packaging_type))}}</td> --}}
                <td>{{$get->size}}</td>
                <td>{{$supplier->name ?? '-'}}</td>
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
                <th>{{$lang->write('Action')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data_ as $item)
                @php
                    $data = DB::table('store_sea')->where('id',$item->in_id)->first();

                    $total = 0;

                    $show = false;

                    if (in_array(auth()->user()->type , ['admin','office_work',])) {
                        $show = true;
                    }else{
                        if(auth()->user()->branch == $clients[$item->client_id]->branch){
                            $show = true;
                        }
                    }

                    if($item->unit === 'cbm'){
                        $total = number_format(floatval($item->price) * floatval($item->cbm),null,'.','');
                    }

                    if($item->unit === 'kg'){
                        $total = number_format(floatval($item->price) * floatval($item->kg),null,'.','');
                    }

                    if($item->plus > 0){
                        $total += floatval($item->plus);
                    }

                    if($item->new_price > 0){
                        $total = number_format(floatval($item->new_price),null,'.','');
                    }
                    
                    $exchange_rate = null;
                    if($item->currency !== 'usd'){
                        $exchange_rate = floatval($currency_exchange_rates[$item->currency]);
                        $total_costs    += $total / $exchange_rate;
                    }else{
                        $total_costs    += $total;
                    }
                    
                    $total_kg       += $item->kg;
                    $total_cbm      += $item->cbm;
                    $total_numbers  += $item->number;

                    $disabled = false;

                    if($item->payment){
                        $disabled = true;

                        $get_branch = DB::table('branches')->select('name')->where('id',$item->branch)->first();
                    }

                    $canceled = $item->canceled === 'true' ? true : false;

                @endphp
                @if ($show)
                    <tr data-id="{{$item->id}}" class="tr_item" data-disabled='{{$disabled ? 'true' : 'false'}}' style="{{$canceled ? 'opacity:.3 ' : ''}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 70px !important" type="hidden" data-id='{{$item->id}}' data-name="kg" class="form-control inp req" value="{{$item->kg}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 70px !important" type="hidden" data-id='{{$item->id}}' data-name="cbm" class="form-control inp req" value="{{$item->cbm}}"> 
                        <input {{$disabled ? 'disabled' : ''}} style="width: 70px !important" type="hidden" data-id='{{$item->id}}' data-name="number" class="form-control inp req" value="{{$item->number}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 80px !important" type="hidden" data-id='{{$item->id}}' data-name="price" class="form-control inp req" value="{{$item->price}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 80px !important" type="hidden" data-id='{{$item->id}}' data-name="plus" class="form-control inp req" value="{{$item->plus}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 80px !important" type="hidden" data-id='{{$item->id}}' data-name="new_price" class="form-control inp req" value="{{$item->new_price}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 80px !important" type="hidden" data-id='{{$item->id}}' data-name="currency" class="form-control inp req" value="{{$item->currency}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 80px !important" type="hidden" data-id='{{$item->id}}' data-name="branch" class="form-control inp req" value="{{$item->branch}}">
                        <input {{$disabled ? 'disabled' : ''}} style="width: 80px !important" type="hidden" data-id='{{$item->id}}' data-name="payment" class="form-control inp req" value="{{$item->payment}}">
                        <input type="hidden" data-name="unit" data-id='{{$item->id}}' value="{{$item->unit}}" class="inp">
                        <input type="hidden" data-name="payment_pending" data-id='{{$item->id}}' value="{{$item->payment_pending}}" class="inp">
                        
                        <td class="{{$clients[$item->client_id]->deleted === 'true' ? 'deleted' : ''}}">{{$clients[$item->client_id]->code ?? '-'}}</td>
                        <td class="{{$clients[$item->client_id]->deleted === 'true' ? 'deleted' : ''}}">{{$clients[$item->client_id]->name ?? '-'}}</td>
                        <td>{{$data->company_name}}</td>
                        <td>{{$lang->write(ucfirst($data->ship_from))}}</td>
                        <td>{{$lang->write(ucfirst($data->type))}}</td>
                        <td>{{$data->category}}</td>
                        <td>{{$lang->write(ucfirst($item->unit))}}</td>
                        <td><span class="total" data-id='{{$item->id}}'>{{$dataController->numberFormat($total)}}</span> <span data-id='{{$item->id}}' class="cur">{{$dataController->get_cur($item->currency , 'symbol')}}</span></td>
                        
                        <td>{{$lang->write(ucfirst($data->receipt))}}</td>
                        <td>{{$lang->write(ucfirst($data->brand))}}</td>
                        <td>{{strlen($data->notes) > 0 ? $data->notes : '-'}}</td>
                        <td>
                            @if (in_array(auth()->user()->type , ['admin','branch_admin'])) 
                                <button {{$disabled ? 'disabled' : ''}} class='btn btn-sm btn-primary' onclick=" {{!$disabled ? 'showContainer_data('.$item->id.')' : ''}}">{{$lang->write('Details')}}</button>
                                
                                @if ($item->payment_pending === 'pending')
                                    <button class='ms-1 btn btn-sm btn-warning' onclick="showConfirmPay({{$item->id}})">{{$lang->write('Pending payment')}}</button>
                                @else
                                    <button {{$disabled ? 'disabled' : ''}} class='ms-1 btn btn-sm btn-secondary' onclick=" {{!$disabled ? 'showPay('.$item->id.')' : ''}}">{{$lang->write('Payment')}}</button>        
                                @endif
                            
                                <button {{$disabled ? '' : 'disabled'}} class='ms-1 btn btn-sm btn-secondary delivery' onclick="delivery({{$item->id}})">{{$lang->write('Delivery')}}</button>
                                <a class='ms-1 btn btn-sm btn-success' target="_blank" href="{{ url('/shipping/stickers/store_sea/' . $item->in_id) }}">{{$lang->write('Stickers')}}</a>
                                <button class='ms-1 btn btn-sm btn-danger cancel' onclick="{{!$canceled ? 'cancel_in_container('.$item->id.')' : ''}}">{{$lang->write('Cancel')}}</button>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

   
    @php
        $third = ceil(count($sea_purpose) / 3);
        $first_half  = array_slice($sea_purpose, 0, $third, true);
        $second_half = array_slice($sea_purpose, $third, $third, true);
        $third_half  = array_slice($sea_purpose, $third * 2, null, true);
    @endphp
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <!-- الجدول الأول -->
        <table class="table" style="flex: 1;">
            <tbody>
                @foreach ($first_half as $key => $item)
                    @php
                        $fee = $fees[$key]; 
                        $total_ = array_sum($fee['result_usd']);

                        foreach ($fee['notes'] as $noteKey => $value) {
                            if(strlen($value) > 0){
                                $fees_notes[]  = $value;
                                $fees_values[] = $fee['value'][$noteKey];
                                $fees_curs[] = $fee['currency'][$noteKey];
                            }
                        }
                        $total_expenses += $total_;
                    @endphp
                    <tr>
                        <th style="width: 300px">{{$lang->write($item)}}</th>
                        <td>{{$dataController->numberFormat($total_)}} $</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- الجدول الثاني -->
        <table class="table" style="flex: 1;">
            <tbody>
                @foreach ($second_half as $key => $item)
                    @php
                        $fee = $fees[$key]; 
                        $total_ = array_sum($fee['result_usd']);

                        foreach ($fee['notes'] as $noteKey => $value) {
                            if(strlen($value) > 0){
                                $fees_notes[]  = $value;
                                $fees_values[] = $fee['result_usd'][$noteKey];
                                $fees_curs[] = $fee['currency'][$noteKey];
                            }
                        }
                        $total_expenses += $total_;
                    @endphp
                    <tr>
                        <th style="width: 300px">{{$lang->write($item)}}</th>
                        <td>{{$dataController->numberFormat($total_)}} $</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- الجدول الثاني -->
        <table class="table" style="flex: 1;">
            <tbody>
                @foreach ($third_half as $key => $item)
                    @php
                        $fee = $fees[$key]; 
                        $total_ = array_sum($fee['result_usd']);

                        foreach ($fee['notes'] as $noteKey => $value) {
                            if(strlen($value) > 0){
                                $fees_notes[]  = $value;
                                $fees_values[] = $fee['result_usd'][$noteKey];
                                $fees_curs[] = $fee['currency'][$noteKey];
                            }
                        }
                        $total_expenses += $total_;
                    @endphp
                    <tr>
                        <th style="width: 300px">{{$lang->write($item)}}</th>
                        <td>{{$dataController->numberFormat($total_)}} $</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>


    <div class="d-flex">
        <table class="table me-2">
            <thead>
                <tr>
                    {{-- <th>{{$lang->write('Total shipping costs')}}</th> --}}
                    <th>{{$lang->write('Total weight')}}</th>
                    <th>{{$lang->write('Total CBM')}}</th>
                    <th>{{$lang->write('Total numbers')}}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    {{-- <td>{{$dataController->numberFormat($total_costs)}} $</td> --}}
                    <td>{{$total_kg}}</td>
                    <td>{{$total_cbm}}</td>
                    <td>{{$total_numbers}}</td>
                </tr>
            </tbody>
        </table>
        
        <table class="table">
            <thead>
                <tr>
                    <th>{{$lang->write('Total profits')}}</th>
                    <th>{{$lang->write('Total expenses')}}</th>
                    <th>{{$lang->write('Net profits')}}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{$dataController->numberFormat($total_costs)}} $</td>
                    <td>{{$dataController->numberFormat($total_expenses)}} $</td>
                    <td>{{$dataController->numberFormat($total_costs - $total_expenses)}} $</td>
                </tr>
            </tbody>
        </table>
    </div>

    
    
    <table class="table">
        <thead>
            <tr>
                <th>{{$lang->write('Notes')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($fees_notes as $key => $item)
                <tr>
                    <td>{{strlen($item) > 0 ? $item . ' /' : ''}} {{$lang->write('Amount')}} : {{$dataController->numberFormat($fees_values[$key])}} {{$dataController->get_cur($fees_curs[$key] , 'symbol')}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection