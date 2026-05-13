@php
      use App\Http\Controllers\settingsController;

    $settingsController = new settingsController();
    $settings = $settingsController->get();
@endphp
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

<div style="display: flex">

    <div style="width: 50%">
        @include('pages.matching.clients')
    </div>
    <div style="width: 50%">
        @include('pages.matching.branches')
    </div>
</div>
