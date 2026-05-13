@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;

@endphp

<table>
    <td style="background: #3a93ac;a56666;color: white;padding:5px 10px;border-right:1px solid #ffffff40">{{$lang->write('Opening balance')}}</td>
    @foreach ($currencies as $item)
        @php

            $plus  = DB::table('treasury_transactions')->where('created_date','<',$date)->where('plus_minus','plus')->where('currency',$item['code']);
            $minus = DB::table('treasury_transactions')->where('created_date','<',$date)->where('plus_minus','minus')->where('currency',$item['code']);

            if(strlen($branch) > 0){
                $plus  = $plus->where('branch',$branch);
                $minus = $minus->where('branch',$branch);
            }

            $plus  = $plus->sum('value');
            $minus = $minus->sum('value');

        @endphp
        
        <td style="background: #a56666;color: white;padding:5px 10px;border-right:1px solid #ffffff40">{{$dataController->numberFormat($plus - $minus)}} {{$item['symbol']}}</td>
    @endforeach
</table>