@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    use App\Http\Controllers\settingsController;
    
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();
    $settingsController = new settingsController();
    $settings = $settingsController->get();

    $currencies = $dataController->currencies;

    $th_style = "background-color: #ebebeb;color:##838383;border: 1px solid #ebebeb;white-space: nowrap;";
    $td_style = "border: 1px solid #ebebeb;";

    $balance = 'balance_'.$currency;

@endphp

@if (count($get) < 1)
    <img src="{{asset('images/empty.png')}}" class="d-block mx-auto" style="width: 300px ; opacity:.5;display:none">
    @php
        return
    @endphp    
@endif

<input type="hidden" data-name="title" class="client_data" value="{{$lang->write('Report')}}">
<input type="hidden" data-name="name" class="client_data" value="{{$client->code}}">
<input type="hidden" data-name="currency" class="client_data" value="{{$lang->write(strtoupper($currency))}}">


<div style="direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};text-align:center">
    
    <div class="d-none">
        @if (env('SHOW_COMPANY_DATA_IN_CLIENT_ALL_REPORT'))
            <div style="display:flex;align-items-center;justify-content:space-between;text-align:{{auth()->user()->lang === 'ar' ? 'right' : 'left'}};direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}}">
                
                <div style="padding-top:20px;">
                    <div style="text-align: center">
                        <img style="width:150px;" src="{{asset('images/mataz.png')}}?ver={{env('VERSION')}}" alt="brand" />        
                        <div style="{{strlen($settings['address']) == 0 ? 'display:none' : ''}}">{{$settings['address']}}</div>
                    </div>
                </div>
                <img style="width:100px;margin:0 20px" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
            </div>

            <h2 style="text-align: center;margin-bottom:30px;font-size:26px">{{$lang->write('Account statement')}}</h2>
        @else
            <img style="width:150px;display:block;margin:auto" class="d-none" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
        @endif
    </div>

    <table class="table" style="width: 100%;">
        <thead>
            <tr>
                <th style="{{$th_style}}">{{$lang->write('Code')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Name')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Phone')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Balance')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Report date')}}</th>
            </tr>
        </thead>
        <tbody>
            <td style="{{$td_style}}">{{$client->code}}</td>
            <td style="{{$td_style}}">{{$client->name}}</td>
            <td style="{{$td_style}}">{{$client->phone}}</td>
            <td style="{{$td_style}}">{{$dataController->numberFormat($client->{$balance})}} {{$dataController->get_cur($currency , 'symbol')}}</td>
            <td style="{{$td_style}}">{{date('Y-m-d')}}</td>
        </tbody>
    </table>
    <table class="table" style="width: 100%;">
        <thead>
            <tr>
                <th style="{{$th_style}}">#</th>
                <th style="{{$th_style}}">{{$lang->write('Action')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Input')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Output')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Remaining balance')}}</th>

                <th style="{{$th_style}}">{{$lang->write('Notes')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Created at')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($get as $item)
                @php
                    $cur  = $dataController->get_cur($item->currency , 'symbol');
                    $data = json_decode($item->data);
                @endphp
                @if ($item->type === 'transfer')
                    @php
                        $cur2 = $dataController->get_cur($item->to_currency , 'symbol');
                    @endphp
                    <tr>
                        <td style="{{$td_style}}">{{$item->auto_id}}</td>
                        <td style="{{$td_style}}">
                            @if (isset($data->from) && isset($data->to) && in_array($item->type , ['deposit','withdraw']))
                                {{$lang->write('Transfer')}}
                            @else
                                {{$dataController->get_type($item->type , $item->plus_minus)}}
                            @endif
                        </td>
                        <td style="{{$td_style}};color:green">
                            @if ($item->to_currency === $currency)
                                {{$dataController->numberFormat($item->transfer_value)}}
                            @else
                                - 
                            @endif
                        </td>
                        <td style="{{$td_style}};color:red">
                            @if ($item->currency === $currency)
                                {{$dataController->numberFormat($item->value)}} 
                            @else
                                - 
                            @endif
                        </td>
                        <td style="{{$td_style}}">
                            @if ($item->currency === $currency)
                                {{$dataController->numberFormat($data->from)}}
                            @endif
                            @if ($item->to_currency === $currency)
                                {{$dataController->numberFormat($data->to)}} 
                            @endif
                        </td>
                
                        <td style="{{$td_style}}">{{$item->notes}}</td>
                        <td style="{{$td_style}}">{{$item->created_date}}</td>
                    </tr>
                @endif
                @if ($item->type !== 'transfer')
                    <tr>
                        <td style="{{$td_style}}">{{$item->auto_id}}</td>
                        <td style="{{$td_style}}">
                            @if (isset($data->from) && isset($data->to) && in_array($item->type , ['deposit','withdraw']))
                                {{$lang->write('Transfer')}}
                            @else
                                {{$dataController->get_type($item->type , $item->plus_minus)}}
                            @endif
                            
                        </td>
                        <td style="{{$td_style}};color:green">
                            @if ($item->plus_minus === 'plus')
                                {{$dataController->numberFormat($item->value)}}
                            @else 
                                -
                            @endif
                        </td>
                        <td style="{{$td_style}};color:red">
                            @if ($item->plus_minus === 'minus')
                                {{$dataController->numberFormat($item->value)}}
                            @else 
                                -
                            @endif
                        </td>
                        <td style="{{$td_style}}">{{$dataController->numberFormat($item->remaining_balance)}} </td>
                 
                        <td style="{{$td_style}}">
                            @if (isset($data->from_client) && isset($data->to_client))
                                @if ($data->to_client == $item->client_id)
                                    <span>{{$lang->write('Transfer from client')}} {{ $dataController->get_client($data->from_client,'code') }}</span>
                                @else
                                    <span class="px-1">{{$lang->write('Transfer to client')}} {{ $dataController->get_client($data->to_client,'code') }}</span>
                                @endif
                                @if (strlen($item->notes) > 0)
                                    <span class="mx-1"> / {{$item->notes}}</span>
                                @endif
                            @else
                                {{$item->notes}}
                            @endif
                        </td>
                        <td style="{{$td_style}}">{{$item->created_date}}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>