@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    use App\Http\Controllers\settingsController;

    $settingsController = new settingsController();
    $settings = $settingsController->get();
    
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();


    $currency_exchange_rates = $dataController->currency_exchange_rates;

    $currencies = $dataController->currencies;
    $currencies = $dataController->shipping_currencies;

    $th_style = "background-color: #ebebeb;color:##838383;border: 1px solid #ebebeb;white-space: nowrap;";
    $td_style = "border: 1px solid #ebebeb;white-space: nowrap;";

    $get   = DB::table('containers_sea')->where('id',$id)->first();
    $data_ = DB::table('store_out_sea')->where('container_id',$id)->get();

    $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
        return DB::table('clients')
            // ->where('deleted', 'false')
            ->select('id', 'name', 'code')
            ->get()
            ->keyBy('id');
    });


    $total_costs   = 0;
    $total_cbm     = 0;
    $total_kg      = 0;
    $total_numbers = 0;

    $total_expenses = 0;

@endphp

<input type="hidden" data-name="title" class="client_data" value="{{$get->number}}">


<div style="direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};text-align:center">
    
    <div class="d-none">
        @if (env('SHOW_COMPANY_DATA_IN_CLIENT_ALL_REPORT'))
            <div style="display:flex;align-items-center;text-align:{{auth()->user()->lang === 'ar' ? 'right' : 'left'}};margin-bottom:30px;direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}}">
                
                <img style="width:100px;margin:0 20px" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
                <div style="padding-top:20px;">
                    <div style="{{strlen($settings['company_name']) == 0 ? 'display:none' : ''}}">{{$settings['company_name']}}</div>
                    <div style="{{strlen($settings['email']) == 0 ? 'display:none' : ''}}">{{$lang->write('Email')}} : {{$settings['email']}}</div>
                    <div style="{{strlen($settings['phone']) == 0 ? 'display:none' : ''}}">{{$lang->write('Phone')}} : {{$settings['phone']}}</div>
                    <div style="{{strlen($settings['address']) == 0 ? 'display:none' : ''}}">{{$lang->write('Address')}} : {{$settings['address']}}</div>
                </div>
            </div>
        @else
            <img style="width:150px;display:block;margin:auto" class="d-none" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
        @endif
    </div>

    <table class="table" style="width: 100%;margin-top:20px">
        <thead>
            <tr>
                <th style="{{$th_style}}">{{$lang->write('Container name')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Container number')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Port of Arrival')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Container size')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Type')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Created at')}}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="{{$td_style}}">{{$get->name}}</td>
                <td style="{{$td_style}}">{{$get->number}}</td>
                <td style="{{$td_style}}">{{$get->arrival}}</td>
                <td style="{{$td_style}}">{{$get->size}}</td>
                <td style="{{$td_style}}">
                    @switch($get->type)
                        @case('full')
                            {{$lang->write('Shared')}}
                        @break
                        @case('custom')
                            @if ($get->commission)
                                {{$lang->write('Custom container with commission')}}
                            @else
                                {{$lang->write('Custom container')}}
                            @endif
                        @break
                    @endswitch
                </td>
                <td style="{{$td_style}}">{{$get->created_date}} {{$get->created_time}}</td>
            </tr>
        </tbody>
    </table>
    <table class="table" style="width: 100%;">
        <thead>
            <tr>
                <th style="{{$th_style}}">{{$lang->write('Client code')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Client name')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Company name')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Shipping from')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Type')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Category')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Unit')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Total cost')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Receipt')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Brand')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Notes')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data_ as $item)
                @php
                    $data = DB::table('store_sea')->where('id',$item->in_id)->first();

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

                    if($item->new_price > 0){
                        $total = floatval($item->new_price);
                    }
                    
                    $exchange_rate = null;
                    if($item->currency !== 'usd'){
                        $exchange_rate = floatval($currency_exchange_rates[$item->currency]);
                        $total_costs    += $total / $exchange_rate;
                    }else{
                        $total_costs    += $total;
                    }

                    $disabled = false;

                    // if($item->payment){
                    //     $disabled = true;

                    //     $get_branch = DB::table('branches')->select('name')->where('id',$item->branch)->first();
                    // }
                @endphp
                <tr>
                    <td style="{{$td_style}}">{{$clients[$item->client_id]->code ?? '-'}}</td>
                    <td style="{{$td_style}}">{{$clients[$item->client_id]->name ?? '-'}}</td>
                    <td style="{{$td_style}}">{{$data->company_name}}</td>
                    <td style="{{$td_style}}">{{$lang->write(ucfirst($data->ship_from))}}</td>
                    <td style="{{$td_style}}">{{$lang->write(ucfirst($data->type))}}</td>
                    <td style="{{$td_style}}">{{$data->category}}</td>
                    <td style="{{$td_style}}">{{$lang->write(ucfirst($item->unit))}}</td>
                    <td style="{{$td_style}}">{{$dataController->numberFormat($total)}}</span> <span data-id='{{$item->id}}' class="cur">{{$dataController->get_cur($item->currency , 'symbol')}}</td>

                    <td style="{{$td_style}}">{{$lang->write(ucfirst($data->receipt))}}</td>
                    <td style="{{$td_style}}">{{$lang->write(ucfirst($data->brand))}}</td>
                    <td style="{{$td_style}}">{{strlen($data->notes) > 0 ? $data->notes : '-'}}</td>
                </tr>
            @endforeach
           
        </tbody>
    </table>

    <br>

    <div style="display: flex">
        <table class="table" style="width: 100%;margin-left:10px">
            <thead>
                <tr>
                    {{-- <th style="{{$th_style}}">{{$lang->write('Total shipping costs')}}</th> --}}
                    <th style="{{$th_style}}">{{$lang->write('Total weight')}}</th>
                    <th style="{{$th_style}}">{{$lang->write('Total CBM')}}</th>
                    <th style="{{$th_style}}">{{$lang->write('Total numbers')}}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    {{-- <td style="{{$td_style}}">{{$dataController->numberFormat($total_costs)}} $</td> --}}
                    <td style="{{$td_style}}">{{$total_kg}}</td>
                    <td style="{{$td_style}}">{{$total_cbm}}</td>
                    <td style="{{$td_style}}">{{$total_numbers}}</td>
                </tr>
            </tbody>
        </table>

        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="{{$th_style}}">{{$lang->write('Total profits')}}</th>
                    <th style="{{$th_style}}">{{$lang->write('Total expenses')}}</th>
                    <th style="{{$th_style}}">{{$lang->write('Net profits')}}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="{{$td_style}}">{{$dataController->numberFormat($total_costs)}} $</td>
                    <td style="{{$td_style}}">{{$dataController->numberFormat($total_expenses)}} $</td>
                    <td style="{{$td_style}}">{{$dataController->numberFormat($total_costs - $total_expenses)}} $</td>
                </tr>
            </tbody>
        </table>
    </div>
    
</div>