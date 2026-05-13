@php
    $timezones = array(
        'Pacific/Midway'       => "(GMT-11:00) Midway Island",
        'US/Samoa'             => "(GMT-11:00) Samoa",
        'US/Hawaii'            => "(GMT-10:00) Hawaii",
        'US/Alaska'            => "(GMT-09:00) Alaska",
        'US/Pacific'           => "(GMT-08:00) Pacific Time (US &amp; Canada)",
        'America/Tijuana'      => "(GMT-08:00) Tijuana",
        'US/Arizona'           => "(GMT-07:00) Arizona",
        'US/Mountain'          => "(GMT-07:00) Mountain Time (US &amp; Canada)",
        'America/Chihuahua'    => "(GMT-07:00) Chihuahua",
        'America/Mazatlan'     => "(GMT-07:00) Mazatlan",
        'America/Mexico_City'  => "(GMT-06:00) Mexico City",
        'America/Monterrey'    => "(GMT-06:00) Monterrey",
        'Canada/Saskatchewan'  => "(GMT-06:00) Saskatchewan",
        'US/Central'           => "(GMT-06:00) Central Time (US &amp; Canada)",
        'US/Eastern'           => "(GMT-05:00) Eastern Time (US &amp; Canada)",
        'US/East-Indiana'      => "(GMT-05:00) Indiana (East)",
        'America/Bogota'       => "(GMT-05:00) Bogota",
        'America/Lima'         => "(GMT-05:00) Lima",
        'America/Caracas'      => "(GMT-04:30) Caracas",
        'Canada/Atlantic'      => "(GMT-04:00) Atlantic Time (Canada)",
        'America/La_Paz'       => "(GMT-04:00) La Paz",
        'America/Santiago'     => "(GMT-04:00) Santiago",
        'Canada/Newfoundland'  => "(GMT-03:30) Newfoundland",
        'America/Buenos_Aires' => "(GMT-03:00) Buenos Aires",
        'Greenland'            => "(GMT-03:00) Greenland",
        'Atlantic/Stanley'     => "(GMT-02:00) Stanley",
        'Atlantic/Azores'      => "(GMT-01:00) Azores",
        'Atlantic/Cape_Verde'  => "(GMT-01:00) Cape Verde Is.",
        'Africa/Casablanca'    => "(GMT) Casablanca",
        'Europe/Dublin'        => "(GMT) Dublin",
        'Europe/Lisbon'        => "(GMT) Lisbon",
        'Europe/London'        => "(GMT) London",
        'Africa/Monrovia'      => "(GMT) Monrovia",
        'Europe/Amsterdam'     => "(GMT+01:00) Amsterdam",
        'Europe/Belgrade'      => "(GMT+01:00) Belgrade",
        'Europe/Berlin'        => "(GMT+01:00) Berlin",
        'Europe/Bratislava'    => "(GMT+01:00) Bratislava",
        'Europe/Brussels'      => "(GMT+01:00) Brussels",
        'Europe/Budapest'      => "(GMT+01:00) Budapest",
        'Europe/Copenhagen'    => "(GMT+01:00) Copenhagen",
        'Europe/Ljubljana'     => "(GMT+01:00) Ljubljana",
        'Europe/Madrid'        => "(GMT+01:00) Madrid",
        'Europe/Paris'         => "(GMT+01:00) Paris",
        'Europe/Prague'        => "(GMT+01:00) Prague",
        'Europe/Rome'          => "(GMT+01:00) Rome",
        'Europe/Sarajevo'      => "(GMT+01:00) Sarajevo",
        'Europe/Skopje'        => "(GMT+01:00) Skopje",
        'Europe/Stockholm'     => "(GMT+01:00) Stockholm",
        'Europe/Vienna'        => "(GMT+01:00) Vienna",
        'Europe/Warsaw'        => "(GMT+01:00) Warsaw",
        'Europe/Zagreb'        => "(GMT+01:00) Zagreb",
        'Europe/Athens'        => "(GMT+02:00) Athens",
        'Europe/Bucharest'     => "(GMT+02:00) Bucharest",
        'Africa/Cairo'         => "(GMT+02:00) Cairo",
        'Africa/Harare'        => "(GMT+02:00) Harare",
        'Europe/Helsinki'      => "(GMT+02:00) Helsinki",
        'Europe/Istanbul'      => "(GMT+02:00) Istanbul",
        'Asia/Jerusalem'       => "(GMT+02:00) Jerusalem",
        'Europe/Kiev'          => "(GMT+02:00) Kyiv",
        'Europe/Minsk'         => "(GMT+02:00) Minsk",
        'Europe/Riga'          => "(GMT+02:00) Riga",
        'Europe/Sofia'         => "(GMT+02:00) Sofia",
        'Europe/Tallinn'       => "(GMT+02:00) Tallinn",
        'Europe/Vilnius'       => "(GMT+02:00) Vilnius",
        'Asia/Baghdad'         => "(GMT+03:00) Baghdad",
        'Asia/Kuwait'          => "(GMT+03:00) Kuwait",
        'Africa/Nairobi'       => "(GMT+03:00) Nairobi",
        'Asia/Riyadh'          => "(GMT+03:00) Riyadh",
        'Europe/Moscow'        => "(GMT+03:00) Moscow",
        'Asia/Tehran'          => "(GMT+03:30) Tehran",
        'Asia/Baku'            => "(GMT+04:00) Baku",
        'Europe/Volgograd'     => "(GMT+04:00) Volgograd",
        'Asia/Muscat'          => "(GMT+04:00) Muscat",
        'Asia/Tbilisi'         => "(GMT+04:00) Tbilisi",
        'Asia/Yerevan'         => "(GMT+04:00) Yerevan",
        'Asia/Kabul'           => "(GMT+04:30) Kabul",
        'Asia/Karachi'         => "(GMT+05:00) Karachi",
        'Asia/Tashkent'        => "(GMT+05:00) Tashkent",
        'Asia/Kolkata'         => "(GMT+05:30) Kolkata",
        'Asia/Kathmandu'       => "(GMT+05:45) Kathmandu",
        'Asia/Yekaterinburg'   => "(GMT+06:00) Ekaterinburg",
        'Asia/Almaty'          => "(GMT+06:00) Almaty",
        'Asia/Dhaka'           => "(GMT+06:00) Dhaka",
        'Asia/Novosibirsk'     => "(GMT+07:00) Novosibirsk",
        'Asia/Bangkok'         => "(GMT+07:00) Bangkok",
        'Asia/Jakarta'         => "(GMT+07:00) Jakarta",
        'Asia/Krasnoyarsk'     => "(GMT+08:00) Krasnoyarsk",
        'Asia/Chongqing'       => "(GMT+08:00) Chongqing",
        'Asia/Hong_Kong'       => "(GMT+08:00) Hong Kong",
        'Asia/Kuala_Lumpur'    => "(GMT+08:00) Kuala Lumpur",
        'Australia/Perth'      => "(GMT+08:00) Perth",
        'Asia/Singapore'       => "(GMT+08:00) Singapore",
        'Asia/Taipei'          => "(GMT+08:00) Taipei",
        'Asia/Ulaanbaatar'     => "(GMT+08:00) Ulaan Bataar",
        'Asia/Urumqi'          => "(GMT+08:00) Urumqi",
        'Asia/Irkutsk'         => "(GMT+09:00) Irkutsk",
        'Asia/Seoul'           => "(GMT+09:00) Seoul",
        'Asia/Tokyo'           => "(GMT+09:00) Tokyo",
        'Australia/Adelaide'   => "(GMT+09:30) Adelaide",
        'Australia/Darwin'     => "(GMT+09:30) Darwin",
        'Asia/Yakutsk'         => "(GMT+10:00) Yakutsk",
        'Australia/Brisbane'   => "(GMT+10:00) Brisbane",
        'Australia/Canberra'   => "(GMT+10:00) Canberra",
        'Pacific/Guam'         => "(GMT+10:00) Guam",
        'Australia/Hobart'     => "(GMT+10:00) Hobart",
        'Australia/Melbourne'  => "(GMT+10:00) Melbourne",
        'Pacific/Port_Moresby' => "(GMT+10:00) Port Moresby",
        'Australia/Sydney'     => "(GMT+10:00) Sydney",
        'Asia/Vladivostok'     => "(GMT+11:00) Vladivostok",
        'Asia/Magadan'         => "(GMT+12:00) Magadan",
        'Pacific/Auckland'     => "(GMT+12:00) Auckland",
        'Pacific/Fiji'         => "(GMT+12:00) Fiji",
    );
    use Illuminate\Support\Facades\DB;
    use App\Http\Controllers\settingsController;
    use App\Http\Controllers\userController;

    $settingsController = new settingsController();
    $settings = $settingsController->get();

    use App\Http\Controllers\langController;

    $lang = new langController();

    if (!in_array(auth()->user()->type , ['admin'])) {
        abort(403, 'Unauthorized');
    }

@endphp
@extends('layout')
@section('content')

    <div class="card p-2">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" style="color:black" aria-current="page" href="general">{{$lang->write('General')}}</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" style="color:black" href="exchange_rates">{{$lang->write('Exchange rates')}}</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" style="color:black" href="about">{{$lang->write('About system')}}</a>
            </li>
        </ul>

        <div class="mt-3">

            <div class="tab" data-tab='general'>
                <form action="{{url('/settings/save')}}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="">{{$lang->write('Time zone')}} :</label>
                        <select name="timezone" id="timezone" class="form-control" style="width: 400px">
                            <?php foreach ($timezones as $key => $value) {?>
                                <option <?php echo ( $settings['timezone'] === $key ) ?'selected' :''?> value="<?php echo $key?>"><?php echo $value?></option>
                            <?php }?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="">{{$lang->write('Logo')}} :</label>
                        <input type="file" class="form-control"  style="width: 400px" value="{{$settings['logo']}}" name="logo" accept=".jpg,.png">
                    </div>
                    <div class="mb-3">
                        <label for="">{{$lang->write('Company name')}} :</label>
                        <input type="text" class="form-control"  style="width: 400px" value="{{$settings['company_name']}}" name="company_name">
                    </div>
                    <div class="mb-3">
                        <label for="">{{$lang->write('Address')}} :</label>
                        <input type="text" class="form-control"  style="width: 400px" value="{{$settings['address']}}" name="address">
                    </div>
                    <div class="mb-3">
                        <label for="">{{$lang->write('Phone')}} :</label>
                        <input type="text" class="form-control"  style="width: 400px" value="{{$settings['phone']}}" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="">{{$lang->write('Email')}} :</label>
                        <input type="text" class="form-control"  style="width: 400px" value="{{$settings['email']}}" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <button class="btn btn-primary submit">{{$lang->write('Save')}} </button>
                    </div>
                </form>
            </div>


            <div class="tab d-none" data-tab='exchange_rates'>
                <form action="{{url('/settings/save2')}}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="">{{$lang->write('Euro exchange rate')}} :</label>
                        <input type="number" step="any"  style="width: 400px" name="currency_eur" class="form-control" value="{{$settings['currency_eur']}}">
                    </div>
                    
                    <div class="mb-3">
                        <label for="">{{$lang->write('Dinar exchange rate')}} :</label>
                        <input type="number" step="any"  style="width: 400px" name="currency_den" class="form-control" value="{{$settings['currency_den']}}">
                    </div>
                    
                    <div class="mb-3">
                        <label for="">{{$lang->write('Yuan exchange rate')}} :</label>
                        <input type="number" step="any"  style="width: 400px" name="currency_cny" class="form-control" value="{{$settings['currency_cny']}}">
                    </div>
                     <button class="btn btn-primary submit">{{$lang->write('Save')}} </button>
                </form>
            </div>
            <div class="tab d-none" data-tab='about'>
                <div class="row mt-5">
                    <div class="col-lg-2 col-12 mb-3">
                        <img class="brand-img d-block mx-auto mb-4" style="width:150px" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
                    </div>
                    <div class="col-lg-10 col-12 mb-3 pt-3">
                        <h2 class="h2">{{env('APP_NAME')}}</h2>
                        <p>{{$lang->write('System version')}} : {{env('VERSION')}}</p>
                        {{-- <p>{{$lang->write('Laravel version')}} : {{app()->version()}}</p>
                        <p>{{$lang->write('PHP version')}} : {{PHP_VERSION}}</p> --}}
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection