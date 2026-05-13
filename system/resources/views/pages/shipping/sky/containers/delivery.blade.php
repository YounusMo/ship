@php
    use Illuminate\Support\Facades\DB;
    use App\Http\Controllers\settingsController;
    use App\Http\Controllers\userController;
    use App\Http\Controllers\dataController;

    $dataController = new dataController();
    $settingsController = new settingsController();
    $settings = $settingsController->get();

    use App\Http\Controllers\langController;

    $lang = new langController();

    $dir = auth()->user()->lang === 'ar' ? 'rtl' : 'ltr';

    $get = DB::table('store_out_sky')->where('id',$id)->first();
    $client = DB::table('clients')->where('id',$get->client_id)->first();

    $data = DB::table('store_sky')->where('id',$get->in_id)->first();

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
<html lang="en" dir='ltr'>
  <head>
    <meta charset="UTF-8" />
    
    <style>
        body {
            font-family: {{auth()->user()->lang === 'zh' ? 'none' : "Segoe UI, Tahoma, Arial, sans-serif"}};
            background: #f2f2f2;
            margin: 0;
            direction: {{$dir}};
            padding: 5px; /* smaller for A5 */
        }
        

      .receipt-wrapper {
        width:100%;
        max-width: 600px; /* ~A5 width on screen */
        /* margin: 0 auto; */
        background: #ffffff;
        /* padding: 15px 20px; */
        box-shadow: 0 0 8px rgba(0, 0, 0, 0.08);
        font-size: 12px;
      }

      .top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 3px solid #c8c8c8;
        padding-bottom: 6px;
        margin-bottom: 10px;
      }

      .logo-placeholder {
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
      }

      .company-details {
        text-align: {{auth()->user()->lang === 'ar' ? 'right' : 'left'}};
        font-size: 11px;
        color: #555;
        line-height: 1.4;
      }

      .tracking-bar {
        background: #ffffff;
        border: 1px solid #c8c8c8;
        padding: 6px 8px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        font-size: 11px;
      }

      .tracking-item {
        font-size: 11px;
      }

      .tracking-label {
        font-weight: 600;
        text-transform: uppercase;
        margin-right: 4px;
      }

      .party-blocks {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
      }

      .party {
        flex: 1;
        border: 1px solid #e0e0e0;
        padding: 8px 9px;
      }

      .party-title {
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 4px;
        border-bottom: 1px solid #c8c8c8;
        padding-bottom: 2px;
        font-size: 11px;
      }

      .party div {
        line-height: 1.4;
        font-size: 11px;
      }

      .section-title {
        font-weight: 700;
        text-transform: uppercase;
        margin: 10px 0 4px;
        font-size: 11px;
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      .info-table th,
      .info-table td {
        padding: 5px 6px;
        border: 1px solid #e0e0e0;
        font-size: 11px;
      }

      .info-table th {
        background: #f7f7f7;
        width: 25%;
        text-align: {{auth()->user()->lang === 'ar' ? 'right' : 'left'}};
      }

      .info-table tr:nth-child(even) td {
        background: #fafafa;
      }

      .charges-table {
        margin-top: 6px;
      }

      .charges-table th,
      .charges-table td {
        padding: 5px 6px;
        border: 1px solid #e0e0e0;
        font-size: 11px;
      }

      .charges-table th {
        background: #f7f7f7;
        text-align: {{auth()->user()->lang === 'ar' ? 'right' : 'left'}};
      }

      .charges-table td.amount {
        text-align: {{auth()->user()->lang === 'ar' ? 'right' : 'left'}};
        white-space: nowrap;
      }

      .charges-table tfoot td {
        font-weight: 700;
        background: #f1f1f1;
      }

      .bottom-row {
        display: flex;
        gap: 10px;
        margin-top: 12px;
      }

      .signatures-box {
        flex: 1;
      }

      .signatures-table {
        width: 100%;
        border-collapse: collapse;
      }

      .signatures-table td {
        border: 1px solid #e0e0e0;
        height: 60px;
        vertical-align: bottom;
        padding: 5px 6px;
        font-size: 10px;
      }

      .sig-label {
        font-weight: 600;
      }

      .terms {
        margin-top: 8px;
        font-size: 9px;
        color: #777;
        line-height: 1.4;
      }

      @media print {
        @page {
          size: A5;
          margin: 5mm 8mm;
        }
        body {
          background: #fff;
          padding: 0;
        }
        .receipt-wrapper {
          box-shadow: none;
          margin: 0;
          max-width: 100%;
        }
      }
    </style>
  </head>
  <body>
    <div class="receipt-wrapper">
      <div class="top-bar">
        <div class="logo-placeholder">
            <img src="{{asset('images/logo.png')}}" style="width:50px">
        </div>
        <div class="company-details">
          <span style="{{strlen($settings['company_name']) == 0 ? 'display:none' : ''}}" >{{ $settings['company_name'] }}</span> <br />
          <span style="{{strlen($settings['address']) == 0 ? 'display:none' : ''}}" >{{ $settings['address'] }}</span> <br />
          <span style="{{strlen($settings['phone']) == 0 ? 'display:none' : ''}}" >{{$lang->write('Phone')}}: {{ $settings['phone'] }} &nbsp;|&nbsp;</span> <span style="{{strlen($settings['email']) == 0 ? 'display:none' : ''}}" >{{$lang->write('Email')}}: {{ $settings['email'] }}</span> 
        </div>
    </div>
      <div class="tracking-bar">
        <div class="tracking-item">
          <span class="tracking-label">{{$lang->write('Date')}}:</span> {{ date('Y-m-d') }}
        </div>
        <div class="tracking-item">
          <span class="tracking-label">{{$lang->write('Service')}}:</span> {{$lang->write('Sky freight')}}
        </div>

        <div class="tracking-item">
          <span class="tracking-label">{{$lang->write('Handled by')}}:</span> {{auth()->user()->name}}
        </div>
      </div>

      <div class="party-blocks">
        <div class="party">
          <div class="party-title">{{$lang->write('Shipper')}}</div>
          <div style="{{strlen($settings['company_name']) == 0 ? 'display:none' : ''}}" >{{$settings['company_name']}}</div>
          <div style="{{strlen($settings['address']) == 0 ? 'display:none' : ''}}" >{{$settings['address']}}</div>
          <div style="{{strlen($settings['phone']) == 0 ? 'display:none' : ''}}" >{{$lang->write('Phone')}} :{{$settings['phone']}}</div>
        </div>
        <div class="party">
          <div class="party-title">{{$lang->write('Consignee')}}</div>
          <div>{{$lang->write('Client Code')}}: <b>{{$client->code}}</b></div>
          <div>{{$lang->write('Client Name')}}: {{$client->name}}</div>
          <div>{{$lang->write('Phone')}}: {{$client->phone}}</div>
        </div>
      </div>

      <div class="section-title">{{$lang->write('Shipment details')}}</div>
      <table class="info-table">
        <tr>
          <th>{{$lang->write('Number')}}</th>
          <td>{{$get->number}}</td>
          <th>{{$lang->write('Type')}}</th>
          <td>{{$lang->write(ucfirst($data->type))}}</td>
        </tr>
        <tr>
          <th>{{$lang->write('Unit')}}</th>
          <td>{{$lang->write(ucfirst($get->unit))}}</td>
          <th>{{$lang->write('Brand')}}</th>
          <td>{{$lang->write(ucfirst($data->brand))}}</td>
        </tr>
        <tr>
          <th>{{$lang->write('Receipt')}}</th>
          <td>{{$lang->write(ucfirst($data->receipt))}}</td>
          <th>{{$lang->write('Weight')}}</th>
          <td>{{$get->kg}} {{$lang->write('KG')}}</td>
        </tr>
        <tr>
          <th>{{$lang->write('Cubic meter')}}</th>
          <td>{{$get->cbm}} CBM</td>
          <th>{{$lang->write('Price')}}</th>
          <td>{{$get->price}}</td>
        </tr>
        <tr>
          <th>{{$lang->write('Additional cost')}}</th>
          <td>{{$dataController->numberFormat($get->plus)}} {{$dataController->get_cur($get->currency , 'symbol')}}</td>
          <th>{{$lang->write('Total')}}</th>
          <td>{{$dataController->numberFormat($total)}} {{$dataController->get_cur($get->currency , 'symbol')}}</td>
        </tr>
      </table>

      

      <div class="bottom-row">
        <div class="signatures-box">
          <table class="signatures-table">
            <tr>
              <td>
                <div class="sig-label">{{$lang->write("Shipper's Signature")}}</div>
              </td>
              <td>
                <div class="sig-label">{{$lang->write("Consignee's Signature")}}</div>
              </td>
            </tr>
          </table>
          <div class="terms">
            {{$lang->write('By signing, the consignee confirms receipt of goods in apparent good
            order and condition. All carriage is subject to the carrier’s
            standard terms and conditions.')}}
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
