@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    use App\Http\Controllers\clientsController;

    $lang = new langController();
    $clientsController = new clientsController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;
    
    $clients  = DB::table('clients')->where('deleted','false')->get();

    $usd_p = 0;
    $eur_p = 0;
    $den_p = 0;
    $cny_p = 0;

    $usd_m = 0;
    $eur_m = 0;
    $den_m = 0;
    $cny_m = 0;

    foreach ($clients as $key => $value) {
        $cl = $clientsController->search_client_balance($value->id , null, null);

        if($cl[0] < 0){
            $usd_m += ($cl[0]);
        }
        
        if($cl[0] > 0){
            $usd_p += $cl[0];
        }

        if($cl[1] < 0){
            $eur_m += ($cl[1]);
        }
        if($cl[1] > 0){
            $eur_p += $cl[1];
        }

        if($cl[2] < 0){
            $cny_m += ($cl[2]);
        }

        if($cl[2] > 0){
            $cny_p += $cl[2];
        }

        if($cl[3] < 0){
            $den_m += ($cl[3]);
        }

        if($cl[3] > 0){
            $den_p += $cl[3];
        }
    }
@endphp
<div style='display:flex'>
    
<table class="table" style="width:25%">
    <thead>
        <tr>
            <th colspan="2" class="text-center h4" style="background-color: #5b5b5b;color:white"><strong>{{$lang->write('USD')}}</strong></th>
        </tr>
    </thead>
    
    <thead>
        <tr>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>+</strong></th>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>-</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($usd_p)}}</td>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($usd_m)}}</td>
        </tr>
        
        <tr>
            <td colspan='2' style='white-space:nowrap;text-align: center;background-color: #ebebeb;'>{{$dataController->numberFormat($usd_p - abs($usd_m))}}</td>
        </tr>
    </tbody>
</table>
    
<table class="table" style="width:25%">
    <thead>
        <tr>
            <th colspan="2" class="text-center h4" style="background-color: #5b5b5b;color:white"><strong>{{$lang->write('RMB')}}</strong></th>
        </tr>
    </thead>
    
    <thead>
        <tr>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>+</strong></th>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>-</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($cny_p)}}</td>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($cny_m)}}</td>
        </tr>
        
        <tr>
            <td colspan='2' style='white-space:nowrap;text-align: center;background-color: #ebebeb;'>{{$dataController->numberFormat($cny_p - abs($cny_m))}}</td>
        </tr>
    </tbody>
</table>
<table class="table" style="width:25%">
    <thead>
        <tr>
            <th colspan="2" class="text-center h4" style="background-color: #5b5b5b;color:white"><strong>{{$lang->write('EUR')}}</strong></th>
        </tr>
    </thead>
    
    <thead>
        <tr>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>+</strong></th>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>-</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($eur_p)}}</td>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($eur_m)}}</td>
        </tr>
        
        <tr>
            <td colspan='2' style='white-space:nowrap;text-align: center;background-color: #ebebeb;'>{{$dataController->numberFormat($eur_p - abs($eur_m))}}</td>
        </tr>
    </tbody>
</table>
<table class="table" style="width:25%">
    <thead>
        <tr>
            <th colspan="2" class="text-center h4" style="background-color: #5b5b5b;color:white"><strong>{{$lang->write('Den')}}</strong></th>
        </tr>
    </thead>
    
    <thead>
        <tr>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>+</strong></th>
            <th class="text-center h4" style="white-space:nowrap;text-align: center;background-color: #dedede;"><strong>-</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($den_p)}}</td>
            <td style="white-space:nowrap;text-align: center;">{{$dataController->numberFormat($den_m)}}</td>
        </tr>
        
        <tr>
            <td colspan='2' style='white-space:nowrap;text-align: center;background-color: #ebebeb;'>{{$dataController->numberFormat($den_p - abs($den_m))}}</td>
        </tr>
    </tbody>
</table>
</div>
<div class='mb-3 d-none' style='display:none'>
    <table class="table" style="width:100%">
        <thead>
            <tr>
                <th colspan="4" class="text-center h4" style="background-color: #5b5b5b;color:white"><strong>{{$lang->write('Clients')}}</strong></th>
            </tr>
        </thead>
        <thead>
            <tr>
                <th style="white-space:nowrap;text-align: left;background-color: #ebebeb;">{{$lang->write('Currency')}}</th>
                <th style="white-space:nowrap;text-align: left;background-color: #ebebeb;">{{$lang->write('Positive balances')}}</th>
                <th style="white-space:nowrap;text-align: left;background-color: #ebebeb;">{{$lang->write('Negative balances')}}</th>
                <th style="white-space:nowrap;text-align: left;background-color: #ebebeb;">{{$lang->write('Difference')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($currencies as $item)
                @php
                    switch ($item['code']) {
                        case 'usd':
                            $pos = $usd_p;
                            $neg = $usd_m;
                            break;
                        case 'eur':
                            $pos = $eur_p;
                            $neg = $eur_m;
                            break;
                        case 'cny':
                            $pos = $cny_p;
                            $neg = $cny_m;
                            break;
                        case 'den':
                            $pos = $den_p;
                            $neg = $den_m;
                            break;
                    }
                    // $pos = $usd_p + $eur_p + $cny_p + $den_p;
                    // $neg = $usd_m + $eur_m + $cny_m + $den_m;
                    $diff = $pos - abs($neg);
                @endphp
                <tr>
                    <td>{{$item['text']}}</td>
                    <td>{{$dataController->numberFormat($pos)}}</td>
                    <td>{{$dataController->numberFormat($neg)}}</td>
                    <td>{{$dataController->numberFormat($diff)}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
