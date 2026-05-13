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

    $name = DB::table('customs_brokers')->where('id',$broker)->first();

    $usd = 0;
    $eur = 0;
    $den = 0;
    $cny = 0;

@endphp

<input type="hidden" data-name="title" class="client_data" value="{{$lang->write('Report')}}">
<input type="hidden" data-name="name" class="client_data" value="{{$name->name}}">

<div style="direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};text-align:center" class="p-0">

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

            {{-- <h2 style="text-align: center;margin-bottom:30px;font-size:26px">{{$lang->write('Account statement')}}</h2> --}}
        @else
            <img style="width:150px;display:block;margin:auto" class="d-none" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
        @endif
    </div>

    <table style="width: 100%;margin-top:20px" class="d-none">
        <tr>
            <td style="text-align: {{auth()->user()->lang === 'ar' ? 'right' : 'left'}}">{{$name->name}}</td>
            <td style="text-align: {{auth()->user()->lang === 'ar' ? 'left' : 'right'}}">{{date('Y-m-d H:i:s')}}</td>
        </tr>
    </table>

    <table class="table mt-0" style="width: 100%; margin-top:20px">
        <thead>
            <tr>
                <th style="{{$th_style}}">#</th>
                <th style="{{$th_style}}">{{$lang->write('Input')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Output')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Type')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Container / Trip number')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Balance')}}</th>
                <th style="{{$th_style}};display:none" class="d-block">{{$lang->write('Notes')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Created at')}}</th>
                <th style="{{$th_style}};display:none" class="d-block">{{$lang->write('Created by')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($get as $item)
                @php
                    $cur = $dataController->get_cur($item->currency , 'symbol');
                    // $data= json_decode($item->data);

                    if($item->currency === 'usd'){
                        if($item->plus_minus === 'plus'){
                            $usd += $item->value;
                        }
                        if($item->plus_minus === 'minus'){
                            $usd -= $item->value;
                        }
                    }

                    if($item->currency === 'eur'){
                        if($item->plus_minus === 'plus'){
                            $eur += $item->value;
                        }
                        if($item->plus_minus === 'minus'){
                            $eur -= $item->value;
                        }
                    }

                    if($item->currency === 'den'){
                        if($item->plus_minus === 'plus'){
                            $den += $item->value;
                        }
                        if($item->plus_minus === 'minus'){
                            $den -= $item->value;
                        }
                    }

                    if($item->currency === 'cny'){
                        if($item->plus_minus === 'plus'){
                            $cny += $item->value;
                        }
                        if($item->plus_minus === 'minus'){
                            $cny -= $item->value;
                        }
                    }

                @endphp
                <tr>
                    <td style="{{$td_style}}">{{$item->auto_id}}</td>
                    <td style="{{$td_style}}">
                        @if ($item->plus_minus === 'plus')
                            {{$dataController->numberFormat($item->value)}} {{$cur}}
                        @else
                        -
                        @endif
                    </td>
                    <td style="{{$td_style}}">
                        @if ($item->plus_minus === 'minus')
                            {{$dataController->numberFormat($item->value)}} {{$cur}}
                        @else
                        -
                        @endif
                    </td>
                    <td style="{{$td_style}};white-space:nowrap">
                        @if ($item->type !== 'deposit')
                            {{$lang->write(ucwords(str_replace(['_'],' ',$item->pay_for)))}}
                        @else 
                            {{$lang->write('Deposit')}}
                        @endif
                    </td>
                    <td style="{{$td_style}}">{{$item->container_number}}</td>
                    <td style="{{$td_style}}">
                        @switch($item->currency)
                            @case('usd')
                                {{$dataController->numberFormat($usd)}} {{$cur}}
                            @break
                            @case('eur')
                                {{$dataController->numberFormat($eur)}} {{$cur}}
                            @break
                            @case('den')
                                {{$dataController->numberFormat($den)}} {{$cur}}
                            @break
                            @case('cny')
                                {{$dataController->numberFormat($cny)}} {{$cur}}
                            @break
                        @endswitch
                    </td>
                    <td style="{{$td_style}};display:none" class="d-block">{{strlen($item->notes) > 0 ? $item->notes : '-'}}</td>
                    <td style="{{$td_style}};white-space:nowrap">{{$item->created_date}} {{$item->created_time}}</td>
                    <td style="{{$td_style}};display:none" class="d-block">{{$users[$item->created_by] ?? '-'}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>