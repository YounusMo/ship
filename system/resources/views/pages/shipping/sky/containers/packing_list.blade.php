@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    use App\Http\Controllers\settingsController;

    $settingsController = new settingsController();
    $settings = $settingsController->get();
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $get   = DB::table('containers_sky')->where('id',$id)->first();

    $data_ = DB::table('store_out_sky')->where('container_id',$id)->get();

    $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
        return DB::table('clients')
            // ->where('deleted', 'false')
            ->select('id', 'name', 'code')
            ->get()
            ->keyBy('id');
    });

    $th_style = "background-color: #ebebeb;color:##838383;border: 1px solid #ebebeb;white-space: nowrap;";
    $td_style = "border: 1px solid #ebebeb;white-space: nowrap;";

@endphp

<div style="padding: 20px;direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};text-align:center">

    <div class="d-none">
        @if (env('SHOW_COMPANY_DATA_IN_CLIENT_ALL_REPORT'))
            <div style="display:flex;align-items-center;text-align:{{auth()->user()->lang === 'ar' ? 'right' : 'left'}};margin-bottom:30px;direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}}">
                
                <img style="width:100px;margin:0 20px" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
                <div style="padding-top:20px;">
                    <div style="{{strlen($settings['company_name']) < 1 ? 'display:none' : ''}}">{{$settings['company_name']}}</div>
                    <div style="{{strlen($settings['email'])< 1 ? 'display:none' : ''}}">{{$lang->write('Email')}} : {{$settings['email']}}</div>
                    <div style="{{strlen($settings['phone'])< 1 ? 'display:none' : ''}}">{{$lang->write('Phone')}} : {{$settings['phone']}}</div>
                    <div style="{{strlen($settings['address'])< 1 ? 'display:none' : ''}}">{{$lang->write('Address')}} : {{$settings['address']}}</div>
                </div>
            </div>
        @else
            <img style="width:150px;display:block;margin:auto" class="d-none" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
        @endif
    </div>

    <table style="width: 100%" border="1px">
        <thead>
            <th style="{{$th_style}}">{{$lang->write('Trip name')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Trip number')}}</th>
        </thead>
        <tbody>
            <tr>
                <td style="{{$td_style}}">{{$get->name}}</td>
                <td style="{{$td_style}}">{{$get->number}}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%" border="1px">
        <thead>
            <thead>
            <tr>
                <th style="{{$th_style}}">{{$lang->write('Client code')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Type')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Category')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Number')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Notes')}}</th>
            </tr>
        </thead>
        </thead>
        <tbody>
            @foreach ($data_ as $item)
                @php
                    $data = DB::table('store_sky')->where('id',$item->in_id)->first();
                @endphp
                
                <tr>
                    <td style="{{$td_style}}">{{$clients[$item->client_id]->code ?? '-'}}</td>
                    <td style="{{$td_style}}">{{$lang->write(ucfirst($data->type))}}</td>
                    <td style="{{$td_style}}">{{$data->category}}</td>
                    <td style="{{$td_style}}">{{$item->number}}</td>
                    <td style="{{$td_style}}">{{strlen($data->notes) > 0 ? $data->notes : '-'}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>