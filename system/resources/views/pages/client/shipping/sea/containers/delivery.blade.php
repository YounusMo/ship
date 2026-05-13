@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $val_style  = "text-align:center;width:300px;border:1px solid #ebebeba1;background-color:#ebebeb;color:black;font-size:20px;font-weight:bold;padding:10px";
    $val_style2 = "font-size:20px;text-align:center;border:1px solid #ebebeba1;background-color:#ebebeba1;color:black;padding:10px";

    $get = DB::table('store_out_sea')->where('id',$id)->first();
    $client = DB::table('clients')->where('id',$get->client_id)->first();

    $data = DB::table('store_sea')->where('out_id',$get->in_id)->first();

    $total_ = 0;
    $total = 0;

    if($get->unit === 'cbm'){
        $total = floatval($get->price * $get->cbm);
        $total_ = floatval($get->price * $get->cbm);
    }

    if($get->unit === 'kg'){
        $total = floatval($get->price * $get->kg);
        $total_ = floatval($get->price * $get->kg);
    }

    if($get->plus > 0){
        $total += floatval($get->plus);
    }
    
@endphp
<div style="padding: 20px;direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};text-align:center">

    <img style="width:150px;display:block;margin:auto" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />

    <table class='table w-100' id='report_tbl' width='100%;' style='margin:auto;text-align:center;'>
        <tbody>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Client name')}}</td>
                <td style='{{$val_style2}}'>{{$client->name}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Shipping type')}}</td>
                <td style='{{$val_style2}}'>{{$lang->write('Sea')}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Client code')}}</td>
                <td style='{{$val_style2}}'>{{$client->code}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Number')}}</td>
                <td style='{{$val_style2}}'>{{$get->number}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Type')}}</td>
                <td style='{{$val_style2}}'>{{$lang->write(ucfirst($data->type))}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Unit')}}</td>
                <td style='{{$val_style2}}'>{{$lang->write(ucfirst($get->unit))}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Brand')}}</td>
                <td style='{{$val_style2}}'>{{$lang->write(ucfirst($data->brand))}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Receipt')}}</td>
                <td style='{{$val_style2}}'>{{$lang->write(ucfirst($data->receipt))}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Weight')}}</td>
                <td style='{{$val_style2}}'>{{$get->kg}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Cubic meter')}}</td>
                <td style='{{$val_style2}}'>{{$get->cbm}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Price')}}</td>
                <td style='{{$val_style2}}'>{{$get->kg}}</td>
            </tr>
            
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Total')}}</td>
                <td style='{{$val_style2}}'>{{$dataController->numberFormat($total_)}} {{$dataController->get_cur($get->currency , 'symbol')}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Additional cost')}}</td>
                <td style='{{$val_style2}}'>{{$dataController->numberFormat($get->plus)}} {{$dataController->get_cur($get->currency , 'symbol')}}</td>
            </tr>
            <tr>
                <td style='{{$val_style}}'>{{$lang->write('Total cost')}}</td>
                <td style='{{$val_style2}}'>{{$dataController->numberFormat($total)}} {{$dataController->get_cur($get->currency , 'symbol')}}</td>
            </tr>
            {{-- <tr>
                <td style='{{$val_style}}'>{{$lang->write('Notes')}}</td>
                <td style='{{$val_style2}}'></td>
            </tr>
             --}}
            {{-- <tr>
                <td style='{{$val_style}}'>ملاحظات</td>
                <td style='{{$val_style2}}'><?php echo $details['pay_notes'] !== '' ? $details['pay_notes']: '-'?></td>
            </tr> --}}
        </tbody>
        </table>
        <table class='table w-100' id='report_tbl' width='100%' style='direction:rtl;text-align:center;margin-top:40px'>
        <tr>
            <td style='{{$val_style}}'>{{$lang->write("Sender's Signature")}}</td>
            <td style='{{$val_style}}'>{{$lang->write("Recipient's signature")}}</td>
        </tr>
        <tr>
            <td style='{{$val_style2}};height:100px'></td>
            <td style='{{$val_style2}};height:100px'></td>
        </tr>
    </table>
</div>