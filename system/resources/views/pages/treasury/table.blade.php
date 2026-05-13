@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();
    $currencies = $dataController->currencies;

    $plus  = DB::table('treasury_transactions')->where('created_date','<',$date)->where('plus_minus','plus')->where('currency',$currency);
    $minus = DB::table('treasury_transactions')->where('created_date','<',$date)->where('plus_minus','minus')->where('currency',$currency);

    if($branch){
        $plus  = $plus->where('branch',$branch);
        $minus = $minus->where('branch',$branch);
    }

    $_plus  = $plus->sum('value');
    $_minus = $minus->sum('value');


    $treasury = $_plus - $_minus;


    $input  = 0;
    $output = 0;


    $th_style = 'background-color: #ebebeb;white-space: nowrap;color: #838383;font-size: 14px;';
    $td_style = 'border: 1px solid #ebebeb;white-space: nowrap;font-size: 14px;';
@endphp
<input type="hidden" class="count" value="{{$count}}">
<table class="table" style="width: 100%">
    <thead>
        <tr>
            <th style="{{$th_style}}">#</th>
            <th style="{{$th_style}}">{{$lang->write('Client code')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Name')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Treasury')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Action')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Input')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Output')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Currency')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Notes')}}</th>
            <th style="{{$th_style}};display:none" class="d-block">{{$lang->write('Created by')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Created at')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php
                $cur = $dataController->get_cur($item->currency , 'symbol');

                if ($item->plus_minus === 'plus') {
                    $input  += $item->value;
                } else {
                    $output += $item->value;
                }
                if($item->type === 'supplier_deposit'){
                    $data_supplier = json_decode($item->data);
                    $get_supplier  = DB::table('suppliers')->select('name')->where('id',$data_supplier->supplier_id)->first();
                }
                if(in_array($item->type , ['customs_deposit' , 'custom_container_deposit'])){
                    $data_broker = json_decode($item->data);
                    $get_broker  = DB::table('customs_brokers')->select('name')->where('id',$data_broker->broker_id)->first();
                }

                $name = $clients[$item->client_id]->code ?? '-';

            @endphp
            <tr>
                <td style="{{$td_style}}">{{$item->auto_id}}</td>
                <td style="{{$td_style}}">{{$name}}</td>
                <td style="{{$td_style}}">
                    @switch($item->type)
                        @case('supplier_deposit')
                            <span>{{$get_supplier->name ?? '-'}}</span> 
                        @break
                        @case('customs_deposit')
                            <span>{{$get_broker->name ?? '-'}}</span> 
                        @break
                        @case('custom_container_deposit')
                            <span>{{$get_broker->name ?? '-'}}</span> 
                        @break
                        @default
                           <span>{{$clients[$item->client_id]->name ?? '-'}}</span> 
                    @endswitch
                </td>
                <td style="{{$td_style}}">
                    @php
                    echo match (auth()->user()->lang) {
                        'ar' => $branches[$item->branch]->name ?? '-',
                        'en' => $branches[$item->branch]->name_en ?? '-',
                        'zh' => $branches[$item->branch]->name_zh ?? '-',
                        default => $branches[$item->branch]->name ?? '-',
                    }
                    @endphp
                </td>
                <td style="{{$td_style}}">{{$dataController->get_type($item->type , $item->plus_minus , $item->data)}}</td>
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
                <td style="{{$td_style}}">{{$dataController->get_cur($item->currency , 'text')}}</td>
                <td style="{{$td_style}}">{{$item->notes}}</td>
                <td style="{{$td_style}};display:none" class="{{$users[$item->created_by] ?? 'deleted'}} d-block">{{$users[$item->created_by] ?? '-'}}</td>
                <td style="{{$td_style}}">{{$item->created_date}} {{$item->created_time}}</td>
            </tr>
        @endforeach
        <tr>
            <td style="background: #f0f0f0" colspan="5"></td>
            <td style="background: #f0f0f0">{{$dataController->numberFormat($input)}}{{$dataController->get_cur($currency , 'symbol')}}</td>
            <td style="background: #f0f0f0">{{$dataController->numberFormat($output)}}{{$dataController->get_cur($currency , 'symbol')}}</td>
            <td style="background: #f0f0f0">{{$dataController->numberFormat(($treasury + ($input - $output)))}}{{$dataController->get_cur($currency , 'symbol')}}</td>
            <td style="background: #f0f0f0" colspan="4"></td>
        </tr>
    </tbody>
</table>

