@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    use App\Http\Controllers\settingsController;

    $settingsController = new settingsController();
    $settings = $settingsController->get();

    $dataController = new dataController();
    $lang = new langController();


    $currency_exchange_rates = $dataController->currency_exchange_rates;
    
    //----------
    //الشحن الجوي
    $total_in_sky_usd       = 0;
    $data_in_sky            = [];
    
    $get_sky = DB::table('containers_sky')->where('canceled','false');

    if($from && $to){
        $get_sky = $get_sky->whereBetween('created_date',[$from,$to]);
    }

    $get_sky = $get_sky->get();

    $tmp = [];
    foreach ($get_sky as $key => $value) {
        $get_sky_data  = DB::table('store_out_sky')->whereNotNull('payment')->where('container_id',$value->id)->get();

        $total  = 0;
        $usd_total = 0;
        foreach ($get_sky_data as $x => $item) {

            if($item->unit === 'cbm'){
                $total = floatval($item->price * $item->cbm);
            }

            if($item->unit === 'kg'){
                $total = floatval($item->price * $item->kg);
            }

            if($item->new_price > 0){
                $total = floatval($item->new_price);
            }

            if($item->plus > 0){
                $total += floatval($item->plus);
            }

            if($item->currency === 'usd'){
                $total_in_sky_usd += $total;
                $usd_total = $total;
            }

            if($item->currency === 'den'){
                $rate = floatval($currency_exchange_rates[$item->currency]);
                $usd_value = number_format((floatval($total) / $rate) , 2 , '.' , '');
                $usd_total = $usd_value;
                $total_in_sky_usd += $usd_value;
            }

            if(in_array($value->id , $tmp)){
                $data_in_sky[$value->id]['total_usd'] += $usd_total;  
            }else{
                $data_in_sky[$value->id] = [
                    'container_id'     => $value->id,
                    'container_number' => $value->number,
                    'total_usd'        => $usd_total,
                ];
                $tmp[] = $value->id;
            }
        }
        
    }
    
    
    //----------
    // الشحن البحري الحاوية المخصصة

    $total_out_sea_custom_usd       = 0;
    $data_out_custom_sea            = [];

    $get_sky = DB::table('containers_sea')->where('type','custom')->where('custom_status','approved')->where('canceled','false');

    if($from && $to){
        $get_sky = $get_sky->whereBetween('created_date',[$from,$to]);
    }

    $get_sky = $get_sky->get();

    $tmp = [];
    foreach ($get_sky as $key => $value) {

        $total  = 0;
        $usd_total = 0;
       $usd_value = 0;

        $total = floatval($value->cost);
        
       
            $total_out_sea_custom_usd += $total;
            $usd_total = $total;
        

        if(in_array($value->id , $tmp)){
            $data_out_custom_sea[$value->id]['total_usd'] += $usd_value;  
        }else{
            $data_out_custom_sea[$value->id] = [
                'container_id'     => $value->id,
                'container_number' => $value->number,
                'total_usd'        => $usd_total,
            ];
            $tmp[] = $value->id;
        }
    }
    //----------
    // الشحن البحري الحاوية المخصصة

    $total_in_sea_custom_usd       = 0;
    $data_in_custom_sea            = [];

    $get_sky = DB::table('containers_sea')->where('type','custom')->where('custom_status','approved')->where('canceled','false');

    if($from && $to){
        $get_sky = $get_sky->whereBetween('created_date',[$from,$to]);
    }

    $get_sky = $get_sky->get();

    $tmp = [];
    foreach ($get_sky as $key => $value) {

        $total  = 0;
        $usd_total = 0;
       $usd_value = 0;

        $total = floatval($value->profit);
        
       
            $total_in_sea_custom_usd += $total;
            $usd_total = $total;
        

        if(in_array($value->id , $tmp)){
            $data_in_custom_sea[$value->id]['total_usd'] += $usd_value;  
        }else{
            $data_in_custom_sea[$value->id] = [
                'container_id'     => $value->id,
                'container_number' => $value->number,
                'total_usd'        => $usd_total,
            ];
            $tmp[] = $value->id;
        }
    }


    //----------
    //الشحن البحري

    $total_in_sea_usd       = 0;
    $data_in_sea            = [];

    $get_sky = DB::table('containers_sea')->where('type','full')->where('canceled','false');

    if($from && $to){
        $get_sky = $get_sky->whereBetween('created_date',[$from,$to]);
    }

    $get_sky = $get_sky->get();

    $tmp = [];
    foreach ($get_sky as $key => $value) {
        $get_sky_data  = DB::table('store_out_sea')->whereNotNull('payment')->where('container_id',$value->id)->get();

        $total  = 0;
        $usd_total = 0;
        foreach ($get_sky_data as $x => $item) {

            $usd_value = 0;

            if($item->unit === 'cbm'){
                $total = floatval($item->price * $item->cbm);
            }

            if($item->unit === 'kg'){
                $total = floatval($item->price * $item->kg);
            }

            if($item->new_price > 0){
                $total = floatval($item->new_price);
            }

            if($item->plus > 0){
                $total += floatval($item->plus);
            }

            if($item->currency === 'usd'){
                $total_in_sea_usd += $total;
                $usd_total = $total;
            }

            if($item->currency === 'den'){
                $rate = floatval($currency_exchange_rates[$item->currency]);
                $usd_value = number_format((floatval($total) / $rate) , 2 , '.' , '');
                $total_in_sea_usd += $usd_value;
                $usd_total = $usd_value;
            }

            if(in_array($value->id , $tmp)){
                $data_in_sea[$value->id]['total_usd'] += $usd_value;  
            }else{
                $data_in_sea[$value->id] = [
                    'container_id'     => $value->id,
                    'container_number' => $value->number,
                    'total_usd'        => $usd_total,
                ];
                $tmp[] = $value->id;
            }
        } 
    }

    //----------
    //الإيداعات

    $total_in_deposit_usd    = 0;
    $data_in_deposit         = [];

    $get_deposits = DB::table('treasury_transactions')->where('type','deposit_commission');

    if($from && $to){
        $get_deposits = $get_deposits->whereBetween('created_date',[$from,$to]);
    }

    $get_deposits = $get_deposits->get();

    $tmp = [];
    foreach ($get_deposits as $key => $value) {
        
        $get_client = DB::table('clients')->where('id',$value->client_id)->first();
    

        $total  = 0;
        $usd_total = 0;

        if($value->currency === 'usd'){
            $total_in_deposit_usd += floatval($value->value);
            $usd_total = floatval($value->value);
        }

        if($value->currency !== 'usd'){
            $rate = floatval($currency_exchange_rates[$value->currency]);
            $usd_value = number_format((floatval($value->value) / $rate) , 2 , '.' , '');
            $total_in_deposit_usd += $usd_value;
            $usd_total = $usd_value;
        }

        if(in_array($get_client->id , $tmp)){
            $data_in_deposit[$get_client->id]['total_usd'] += $usd_total;  
        }else{
            $data_in_deposit[$get_client->id] = [
                'client_name'      => $get_client->name,
                'client_code'      => $get_client->code,
                'total_usd'        => $usd_total,
            ];
            $tmp[] = $get_client->id;
        }
    }

    //----------
    //المصروفات الشركة
    
    $total_out_company_usd    = 0;
    $data_out_company         = [];

    $get_transactions = DB::table('branches_transactions')->where('type','expenses_branch');

    if($from && $to){
        $get_transactions = $get_transactions->whereBetween('created_date',[$from,$to]);
    }

    $get_transactions = $get_transactions->get();

    $tmp = [];
    foreach ($get_transactions as $key => $value) {
        
        $data = json_decode($value->data);

        $perpose = $data->purpose;


        $total  = 0;
        $usd_total = 0;

        if($value->currency === 'usd'){
            $total_out_company_usd += floatval($value->value);
            $usd_total = floatval($value->value);
        }

        if($value->currency !== 'usd'){
            $rate = floatval($currency_exchange_rates[$value->currency]);
            $usd_value = number_format((floatval($value->value) / $rate) , 2 , '.' , '');
            $total_out_company_usd += $usd_value;
            $usd_total = $usd_value;
        }

        if(in_array($perpose , $tmp)){
            $data_out_company[$perpose]['total_usd'] += $usd_total;  
        }else{
            if($perpose === 'salary'){
                $get_user = DB::table('users')->where('id',$data->users)->first();
                $perpose_txt = $lang->write('Salary') . ' / ' . ($get_user->name ?? '-');
            }else{
                $perpose_txt = $lang->write(ucwords(str_replace(['_'] , ' ',$data->purpose)));
            }

            $data_out_company[$perpose] = [
                'perpose'      => $perpose_txt,
                'total_usd'    => $usd_total,
            ];
            $tmp[] = $perpose;
        }
    }

    //----------
    // مصروفات الشحن البحري

    $total_out_sea_usd       = 0;
    $data_out_sea            = [];

    $get_sea = DB::table('containers_sea')->where('type','full')->where('canceled','false');

    if($from && $to){
        $get_sea = $get_sea->whereBetween('created_date',[$from,$to]);
    }

    $get_sea = $get_sea->get();

    $tmp = [];
    foreach ($get_sea as $key => $value) {
        $get_sea_data  = DB::table('containers_sea_fees')
        ->whereNotIn('purpose',['container_fee_value'])
        ->where('container_id',$value->id)->get();
        
        $total  = 0;
        $usd_total = 0;
        foreach ($get_sea_data as $x => $item) {

            $total_out_sea_usd += $item->result_usd;
            $usd_total = $item->result_usd;

            if(in_array($value->id , $tmp)){
                $data_out_sea[$value->id]['total_usd'] += $usd_total;  
            }else{
                $data_out_sea[$value->id] = [
                    'container_id'     => $value->id,
                    'container_number' => $value->number,
                    'total_usd'        => $usd_total,
                ];
                $tmp[] = $value->id;
            }
        } 
    }
    
    //----------
    // مصروفات الشحن الجوي

    $total_out_sky_usd       = 0;
    $data_out_sky            = [];

    $get_sky = DB::table('containers_sky')->where('canceled','false');

    if($from && $to){
        $get_sky = $get_sky->whereBetween('created_date',[$from,$to]);
    }

    $get_sky = $get_sky->get();

    $tmp = [];
    foreach ($get_sky as $key => $value) {
        $get_sky_data  = DB::table('containers_sky_fees')
        ->whereNotIn('purpose',['container_fee_value'])
        ->where('container_id',$value->id)->get();
        
        $total  = 0;
        $usd_total = 0;
        foreach ($get_sky_data as $x => $item) {

            $total_out_sky_usd += $item->result_usd;
            $usd_total = $item->result_usd;

            if(in_array($value->id , $tmp)){
                $data_out_sky[$value->id]['total_usd'] += $usd_total;  
            }else{
                $data_out_sky[$value->id] = [
                    'container_id'     => $value->id,
                    'container_number' => $value->number,
                    'total_usd'        => $usd_total,
                ];
                $tmp[] = $value->id;
            }
        } 
    }
    

    //----------
    //المخلصين الجمركيين

    $total_out_brokers_usd    = 0;
    $data_out_brokers         = [];

    $get_brokers = DB::table('customs_brokers_transactions');

    if($from && $to){
        $get_brokers = $get_brokers->whereBetween('created_date',[$from,$to]);
    }

    $get_brokers = $get_brokers->get();

    $tmp = [];
    foreach ($get_brokers as $key => $value) {
        
        $get_broker= DB::table('customs_brokers')->where('id',$value->broker_id)->first();
    
        $total  = 0;
        $usd_total = 0;

        if($value->currency === 'usd'){
            if($value->plus_minus === 'plus'){
                $total_out_brokers_usd += floatval($value->value);
                $usd_total += floatval($value->value);
            }else{
                $total_out_brokers_usd -= floatval($value->value);
                $usd_total -= floatval($value->value);
            }

            
        }

        if($value->currency !== 'usd'){
            $rate = floatval($currency_exchange_rates[$value->currency]);
            $usd_value = number_format((floatval($value->value) / $rate) , 2 , '.' , '');
            $total_out_brokers_usd += $usd_value;
            $usd_total = $usd_value;
        }

        if(in_array($get_broker->id , $tmp)){
            $data_out_brokers[$get_broker->id]['total_usd'] += $usd_total;  
        }else{
            $data_out_brokers[$get_broker->id] = [
                'broker_name'      => $get_broker->name,
                'total_usd'        => $usd_total,
            ];
            $tmp[] = $get_broker->id;
        }
    }
    //----------
    //الموردين

    $total_out_suppliers_usd    = 0;
    $data_out_suppliers         = [];

    $get_suppliers = DB::table('suppliers_transactions')->where('plus_minus','plus');

    if($from && $to){
        $get_suppliers = $get_suppliers->whereBetween('created_date',[$from,$to]);
    }

    $get_suppliers = $get_suppliers->get();

    $tmp = [];
    foreach ($get_suppliers as $key => $value) {
        
        $get_supplier= DB::table('suppliers')->where('id',$value->supplier_id)->first();
    
        $total  = 0;
        $usd_total = 0;

        if($value->currency === 'usd'){
            $total_out_suppliers_usd += floatval($value->value);
            $usd_total = floatval($value->value);
        }

        if($value->currency !== 'usd'){
            $rate = floatval($currency_exchange_rates[$value->currency]);
            $usd_value = number_format((floatval($value->value) / $rate) , 2 , '.' , '');
            $total_out_suppliers_usd += $usd_value;
            $usd_total = $usd_value;
        }

        if(in_array($get_supplier->id , $tmp)){
            $data_out_suppliers[$get_supplier->id]['total_usd'] += $usd_total;  
        }else{
            $data_out_suppliers[$get_supplier->id] = [
                'supplier_name'      => $get_supplier->name,
                'total_usd'        => $usd_total,
            ];
            $tmp[] = $get_supplier->id;
        }
    }

    
    $arrow = '';

    if(auth()->user()->lang === 'ar'){
        $arrow = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M31 36L19 24l12-12" />
        </svg>';
    }else{
        
        $arrow = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
            <g fill="none" fill-rule="evenodd">
                <path d="M24 0v24H0V0zM12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035q-.016-.005-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.017-.018m.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01z" />
                <path fill="currentColor" d="M15.707 11.293a1 1 0 0 1 0 1.414l-5.657 5.657a1 1 0 1 1-1.414-1.414l4.95-4.95l-4.95-4.95a1 1 0 0 1 1.414-1.414z" />
            </g>
        </svg>';
    }

@endphp
<input type="hidden" class="count" value="0">
<style>
    .toggler{
        background:transparent;
        border:0
    }
    .border-hide{
        border:0 !important;
    }
</style>
 <div class="d-none">
    @if (env('SHOW_COMPANY_DATA_IN_CLIENT_ALL_REPORT'))
        <div style="display:flex;align-items-center;justify-content:space-between;margin-bottom:30px;text-align:{{auth()->user()->lang === 'ar' ? 'right' : 'left'}};direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}}">
            
            <div style="padding-top:20px;">
                <div style="text-align: center">
                    <img style="width:150px;" src="{{asset('images/mataz.png')}}?ver={{env('VERSION')}}" alt="brand" />        
                    <div>{{$settings['address']}}</div>
                </div>
            </div>
            <img style="width:100px;margin:0 20px" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
        </div>
    @else
        <img style="width:150px;display:block;margin:auto" class="d-none" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
    @endif
</div>
<div class="row" style="direction: {{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};">
    <div class="col-lg-4 col-12 mb-3">
        <table class="table" style="width:100%">
            <thead>
                <th colspan="2" class="text-center" style="border-bottom:1px solid #d2d2d2;background:#7a7979;color:white">{{$lang->write('Profit')}}</th>
            </thead>
            <thead>
                <th class="px-4">{{$lang->write('Revenue')}}</th>
                <th class="text-center">{{$lang->write('Amount')}}</th>
            </thead>
            <tbody>
                <tr class='main_tr' data-tab='sky'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='sky'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Air freight')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_in_sky_usd)}} $</td>
                </tr>
                @foreach ($data_in_sky as $item)
                    <tr class='d-none tab' data-tab='sky' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['container_number']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr class='main_tr' data-tab='sea'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='sea'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Sea freight')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_in_sea_usd)}} $</td>
                </tr>
                @foreach ($data_in_sea as $item)
                    <tr class='d-none tab' data-tab='sea' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['container_number']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr class='main_tr' data-tab='sea_'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='sea_'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Sea freight (Custom)')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_in_sea_custom_usd)}} $</td>
                </tr>
                @foreach ($data_in_custom_sea as $item)
                    <tr class='d-none tab' data-tab='sea_' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['container_number']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr class='main_tr' data-tab='deposits'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='deposits'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Deposit commissions')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_in_deposit_usd)}} $</td>
                </tr>
                @foreach ($data_in_deposit as $item)
                    <tr class='d-none tab' data-tab='deposits' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['client_code']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr style='background: #ececec;'>
                    <th style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$lang->write('Total')}}</th>
                    <th class="text-center center">{{$dataController->NumberFormat($total_in_sea_usd + $total_in_sky_usd + $total_in_deposit_usd + $total_in_sea_custom_usd)}} $</th>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="col-lg-4 col-12 mb-3">
        <table class="table" style="width:100%">
            <thead>
                <th colspan="2" class="text-center" style="border-bottom:1px solid #d2d2d2;background:#7a7979;color:white">{{$lang->write('Expenses')}}</th>
            </thead>
            <thead>
                <th class="px-4">{{$lang->write('Expenses')}}</th>
                <th class="text-center">{{$lang->write('Amount')}}</th>
            </thead>
            <tbody>
                <tr class='main_tr' data-tab='company'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='company'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Company expenses')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_out_company_usd)}} $</td>
                </tr>
                @foreach ($data_out_company as $item)
                    <tr class='d-none tab' data-tab='company' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['perpose']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr class='main_tr' data-tab='sea2'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='sea2'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Sea freight')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_out_sea_usd)}} $</td>
                </tr>
                @foreach ($data_out_sea as $item)
                    <tr class='d-none tab' data-tab='sea2' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['container_number']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                
                {{-- <tr class='main_tr' data-tab='sea_2'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='sea_2'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Sea freight (Custom)')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_out_sea_custom_usd)}} $</td>
                </tr>
                @foreach ($data_out_custom_sea as $item)
                    <tr class='d-none tab' data-tab='sea_2' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['container_number']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach --}}
                <tr class='main_tr' data-tab='sky2'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='sky2'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Air freight')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_out_sky_usd)}} $</td>
                </tr>
                @foreach ($data_out_sky as $item)
                    <tr class='d-none tab' data-tab='sky2' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['container_number']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr class='main_tr' data-tab='custom_brokers'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='custom_brokers'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Customs Clearance')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_out_brokers_usd)}} $</td>
                </tr>
                @foreach ($data_out_brokers as $item)
                    <tr class='d-none tab' data-tab='custom_brokers' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['broker_name']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr class='main_tr' data-tab='suppliers'>
                    <td class='hidd'>
                        <button class='toggler' data-tab='suppliers'>
                            <span class="left">
                                {!! $arrow !!}
                            </span>
                            <span class="down d-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M36 18L24 30L12 18" />
                                </svg>
                            </span>
                        </button>
                        {{$lang->write('Shipping lines')}}
                    </td>
                    <td class="text-center center">{{$dataController->NumberFormat($total_out_suppliers_usd + $total_out_sea_custom_usd)}} $</td>
                </tr>
                @foreach ($data_out_suppliers as $item)
                    <tr class='d-none tab' data-tab='suppliers' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['supplier_name']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                @foreach ($data_out_custom_sea as $item)
                    <tr class='d-none tab' data-tab='suppliers' style='background: #ececec;'>
                        <td style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$item['container_number']}}</td>
                        <td class="text-center center">{{$dataController->NumberFormat($item['total_usd'])}} $</td>
                    </tr>
                @endforeach
                <tr style='background: #ececec;'>
                    <th style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$lang->write('Total')}}</th>
                    <th class="text-center center">{{$dataController->NumberFormat($total_out_company_usd + $total_out_sea_usd+ $total_out_sky_usd+ $total_out_brokers_usd+ $total_out_suppliers_usd + $total_out_sea_custom_usd)}} $</th>
                </tr>
            </tbody>
        </table>
        </div>
        <div class="col-lg-4 col-12 mb-3">
            <table class="table" style="width:100%">
                <thead>
                    <th colspan="2" class="text-center" style="border-bottom:1px solid #d2d2d2;background:#7a7979;color:white">{{$lang->write('Profits')}}</th>
                </thead>
                <thead>
                    <th class="text-center center">{{$lang->write('Profits')}}</th>
                    <th class="text-center center">{{$lang->write('Expenses')}}</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center center">{{$dataController->NumberFormat($total_in_sea_usd + $total_in_sky_usd + $total_in_deposit_usd +$total_in_sea_custom_usd)}} $</td>
                        <td class="text-center center">{{$dataController->NumberFormat($total_out_company_usd + $total_out_sea_usd + $total_out_sky_usd+ $total_out_brokers_usd+ $total_out_suppliers_usd+$total_out_sea_custom_usd)}} $</td>
                    </tr>
                    <tr style='background: #ececec;'>
                        <th style="{{auth()->user()->lang === 'ar' ? 'padding-right' : 'padding-left'}}: 20px">{{$lang->write('Total')}}</th>
                        <th class="text-center">{{$dataController->NumberFormat(($total_in_sea_usd + $total_in_sky_usd + $total_in_deposit_usd+$total_in_sea_custom_usd)-($total_out_company_usd + $total_out_sea_usd+ $total_out_sky_usd+ $total_out_brokers_usd+ $total_out_suppliers_usd + $total_out_sea_custom_usd))}} $</th>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>