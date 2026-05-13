@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    use App\Http\Controllers\settingsController;

    $settingsController = new settingsController();
    $settings = $settingsController->get();
    $lang = new langController();

    $dataController = new dataController();
    $currencies = $dataController->currencies;
    $th_style = 'background-color: #ebebeb;white-space: nowrap;color: #838383;font-size: 14px;';
    $td_style = 'border: 1px solid #ebebeb;font-size: 14px;';
@endphp
<input type="hidden" class="count" value="{{$count}}">

@if (count($get) < 1)
    <img src="{{asset('images/empty.png')}}" class="d-block mx-auto" style="width: 500px ; opacity:.5">
    @php
        return
    @endphp    
@endif
 <div class="d-none">
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
</div>
<table class="table" style="width: 100%">
    <thead>
        <tr>
            <th style="{{$th_style}}">{{$lang->write('Trip number')}}</th>
            <th style="{{$th_style}}">{{$lang->write('The purpose of the withdrawal')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Treasury')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Amount')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Currency')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Exchange rate')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Notes')}}</th>
            <th style="{{$th_style}};display:none" class="d-block">{{$lang->write('Created by')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Created at')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            <tr>
                <td style="{{$td_style}}">{{$item->container_number}}</td>
                <td style="{{$td_style}}">{{$lang->write(ucwords(str_replace(['_'] , ' ',$item->purpose)))}}</td>
                <td style="{{$td_style}}">
                    @php
                    echo match (auth()->user()->lang) {
                        'ar' => $branches[$item->branch]->name ?? 'اسم غير متوفر',
                        'en' => $branches[$item->branch]->name_en ?? 'Name not available',
                        'zh' => $branches[$item->branch]->name_zh ?? '名称不可用',
                        default => $branches[$item->branch]->name ?? 'اسم غير متوفر',
}
                    @endphp
                </td>
                <td style="{{$td_style}}">{{$dataController->numberFormat($item->value)}}</td>
                <td style="{{$td_style}}">{{$dataController->get_cur($item->currency , 'text')}}</td>
                <td style="{{$td_style}}">{{$dataController->numberFormat($item->exchange_rate)}}</td>
                <td style="{{$td_style}}">{{$item->notes}}</td>
                <td style="{{$td_style}};display:none" class="d-block">{{$users[$item->created_by] ?? '-'}}</td>
                <td style="{{$td_style}}">{{$item->created_date}} {{$item->created_time}}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>