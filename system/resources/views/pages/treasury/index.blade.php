@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use App\Http\Controllers\settingsController;

    $settingsController = new settingsController();
    $settings = $settingsController->get();

    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;

    $branches = DB::table('branches');
    $branches = $branches->where('deleted', 'false');
    if (in_array(auth()->user()->type , ['branch_admin'])) {
        $branches = $branches->where('id', auth()->user()->branch);
    }
    $branches = $branches->orderBy('id', 'DESC');
    $branches = $branches->get();
    $branches = $branches->map(function ($branch)use($lang) {
        return [
            'val' => (string) $branch->id,
            'txt' => $lang->branch($branch->id),
        ];
    })
    ->toArray();

    $branch_name = $lang->write('All');
    $branch_id = '';
    if(in_array(auth()->user()->type , ['branch_admin'])){
        $branch_id = auth()->user()->branch;
        $get_ = DB::table('branches')->where('id',auth()->user()->branch)->first();
        if($get_){
            $branch_name = $lang->branch($get_->id);    
        }
    }

    if (!in_array(auth()->user()->type , ['admin'])) {
        abort(403, 'Unauthorized');
    }
@endphp
@extends('layout')
@section('content')
    <div class="treasury">
        <div class="row d-flex align-items-center">
            <div class="col-lg-4 col-12 mb-2">
                <div class="d-flex align-items-center">
                    <h4 class="h4">{{$lang->write('Treasury')}}</h4>
                    <span class="table_counter">0</span>
                </div>
            </div>
            <div class="col-lg-8 col-12 mb-2 text-end">
                <div class="d-flex align-items-center justify-content-end">
                    <div class="w-25 text-start branch">
                        <label for="">{{$lang->write('Branch')}} :</label>
                        {!! $dataController->sys_selector('branch',$branches , $branch_id ,in_array(auth()->user()->type , ['branch_admin']) ? false: true , $branch_name) !!}
                    </div>
                    <div class="w-25 text-start mx-2">
                        <label for="">{{$lang->write('Currency')}} :</label>
                        <select class="form-select currency">
                            @foreach ($currencies as $item)
                                <option value="{{$item['code']}}">{{$item['text']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-25 text-start branch mx-2">
                        <label for="">{{$lang->write('From')}} :</label>
                        <input type="date" class="form-control date" value="{{date('Y-m-d')}}">
                    </div>
                    <div class="w-25 text-start branch mx-2">
                        <label for="">{{$lang->write('To')}} :</label>
                        <input type="date" class="form-control date2" value="{{date('Y-m-d')}}">
                    </div>
                    <div class="text-start pt-3 mx-2">
                        <button class="btn btn-primary print">{{$lang->write('Print')}}</button>
                    </div>
                </div>
            </div>
        </div>


        <div id="printable" style="direction: {{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};">

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
            
            <div class="balances_">
                <table>
                    <td style="background: #3a93ac;a56666;color: white;padding:5px 10px;border-right:1px solid #ffffff40">{{$lang->write('Opening balance')}}</td>
                    @foreach ($currencies as $item)
                        <td style="background: #a56666;color: white;padding:5px 10px;border-right:1px solid #ffffff40">0.00 {{$item['symbol']}}</td>
                    @endforeach
                </table>
            </div>
            
            
            <div class="main-table mt-2">
                
            </div>
        </div>
        
    </div>
@endsection