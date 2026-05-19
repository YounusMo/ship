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

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $lang->write('Settings') }}</h1>
        <div class="page-subtitle">{{ $lang->write('Company details, exchange rates and system info') }}</div>
    </div>
</div>

<div class="card">
    <ul class="nav nav-tabs" style="border-bottom:1px solid var(--color-border);padding:0 var(--space-4);">
        <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="general" style="color:var(--color-navy-800);font-weight:600;">{{ $lang->write('General') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="exchange_rates" style="color:var(--color-text-muted);">{{ $lang->write('Exchange rates') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="about" style="color:var(--color-text-muted);">{{ $lang->write('About system') }}</a>
        </li>
    </ul>

    <div class="card-body">

        {{-- ====== General ====== --}}
        <div class="tab" data-tab='general'>
            <form action="{{ url('/settings/save') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3" style="max-width: 720px;">
                    <div class="col-12">
                        <label class="form-label">{{ $lang->write('Time zone') }}</label>
                        <select name="timezone" id="timezone" class="form-select">
                            @foreach ($timezones as $key => $value)
                                <option {{ $settings['timezone'] === $key ? 'selected' : '' }} value="{{ $key }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ $lang->write('Logo') }}</label>
                        <input type="file" class="form-control" name="logo" accept=".jpg,.png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Company name') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['company_name'] }}" name="company_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Email') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['email'] }}" name="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Phone') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['phone'] }}" name="phone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Address') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['address'] }}" name="address">
                    </div>

                    <div class="col-12">
                        <div class="divider-labeled">{{ $lang->write('Legal & receipt information') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Commercial registry no.') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['commercial_registry'] ?? '' }}" name="commercial_registry" placeholder="السجل التجاري">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Tax ID') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['tax_id'] ?? '' }}" name="tax_id" placeholder="الرقم الضريبي">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ $lang->write('Receipt footer note') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['receipt_footer'] ?? '' }}" name="receipt_footer" placeholder="{{ $lang->write('Optional line at the bottom of every receipt — e.g. thank-you note or return policy') }}">
                    </div>

                    <div class="col-12">
                        <div class="divider-labeled">{{ $lang->write('Tracking stickers') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Tracking code prefix') }}</label>
                        <input type="text" class="form-control" value="{{ $settings['tracking_prefix'] ?? '' }}" name="tracking_prefix" maxlength="5" placeholder="{{ $lang->write('e.g. SHIP — 2-5 letters/digits, auto-derived from company name if blank') }}">
                        <small class="text-muted">{{ $lang->write('Appears in front of every tracking code, e.g. PREFIX-AB12-CD34-EF56. Defaults to your company initials.') }}</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ $lang->write('Print confirmation PIN') }}</label>
                        <input type="password" class="form-control" name="print_pin" maxlength="8" autocomplete="new-password"
                               placeholder="{{ !empty($settings['print_pin_hash']) ? $lang->write('•••• (set — leave blank to keep)') : $lang->write('4-8 digits — required to bulk-print stickers') }}">
                        <small class="text-muted">{{ $lang->write('Operators must enter this PIN to print all stickers for a container at once. Leave blank to keep the current PIN.') }}</small>
                        @error('print_pin')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary submit">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-inline-end:6px;vertical-align:-2px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        {{ $lang->write('Save') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- ====== Exchange rates ====== --}}
        <div class="tab d-none" data-tab='exchange_rates'>
            <div class="kpi-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
                <div class="kpi-tile">
                    <div class="kpi-label">EUR / USD</div>
                    <div class="kpi-value" style="font-size:var(--fs-2xl);">{{ $settings['currency_eur'] }}</div>
                    <div class="kpi-sub"><span class="currency-badge eur">EUR</span></div>
                </div>
                <div class="kpi-tile">
                    <div class="kpi-label">LYD / USD</div>
                    <div class="kpi-value" style="font-size:var(--fs-2xl);">{{ $settings['currency_den'] }}</div>
                    <div class="kpi-sub"><span class="currency-badge den">LYD</span></div>
                </div>
                <div class="kpi-tile">
                    <div class="kpi-label">CNY / USD</div>
                    <div class="kpi-value" style="font-size:var(--fs-2xl);">{{ $settings['currency_cny'] }}</div>
                    <div class="kpi-sub"><span class="currency-badge cny">CNY</span></div>
                </div>
            </div>

            <form action="{{ url('/settings/save2') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3" style="max-width: 720px;">
                    <div class="col-md-4">
                        <label class="form-label">{{ $lang->write('Euro exchange rate') }}</label>
                        <input type="number" step="any" name="currency_eur" class="form-control" value="{{ $settings['currency_eur'] }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ $lang->write('Dinar exchange rate') }}</label>
                        <input type="number" step="any" name="currency_den" class="form-control" value="{{ $settings['currency_den'] }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ $lang->write('Yuan exchange rate') }}</label>
                        <input type="number" step="any" name="currency_cny" class="form-control" value="{{ $settings['currency_cny'] }}">
                    </div>
                </div>
                <small class="text-muted d-block mt-2">{{ $lang->write('All rates are expressed against 1 USD. Every change is recorded in the audit log.') }}</small>
                <div class="mt-4">
                    <button class="btn btn-primary submit">{{ $lang->write('Save') }}</button>
                </div>
            </form>
        </div>

        {{-- ====== About ====== --}}
        <div class="tab d-none" data-tab='about'>
            <div class="d-flex align-items-start gap-4 mt-3">
                @php
                    $hasLogo = \App\Http\Controllers\settingsController::brandLogoPath();
                    $initial = \App\Http\Controllers\settingsController::brandInitial($settings);
                @endphp
                @if ($hasLogo)
                    <img src="{{ asset('images/logo.png') }}" alt="{{ $settings['company_name'] ?? '' }}" style="width:72px;height:72px;border-radius:var(--radius-lg);object-fit:contain;background:#fff;flex-shrink:0;">
                @else
                    <div style="width:72px;height:72px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--color-navy-800),var(--color-navy-900));color:var(--color-gold-500);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:28px;flex-shrink:0;">{{ $initial }}</div>
                @endif
                <div>
                    <h2 class="h4 mb-1">{{ env('APP_NAME') }}</h2>
                    <div class="text-muted mb-3">{{ $lang->write('Multi-branch shipping & treasury operations') }}</div>
                    <div style="display:grid;grid-template-columns:auto auto;gap:8px 24px;font-size:var(--fs-sm);">
                        <span class="text-muted">{{ $lang->write('System version') }}</span>
                        <span class="text-strong">{{ env('VERSION') }}</span>
                        <span class="text-muted">{{ $lang->write('Laravel version') }}</span>
                        <span class="text-strong">{{ app()->version() }}</span>
                        <span class="text-muted">{{ $lang->write('PHP version') }}</span>
                        <span class="text-strong">{{ PHP_VERSION }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection