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

    <div class="page-header">
        <div>
            <h1 class="page-title">
                {{ $lang->write('Treasury') }}
                <span class="table_counter text-muted" style="font-size:var(--fs-lg);font-weight:500;margin-inline-start:8px;">0</span>
            </h1>
            <div class="page-subtitle">
                {{ $lang->write('Cash movements per branch and currency') }}
            </div>
        </div>
        <div class="page-actions">
            <button class="btn btn-primary print">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                {{ $lang->write('Print') }}
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="toolbar" style="align-items:flex-end;">
        <div class="branch" style="flex:1 1 220px;min-width:200px;">
            <label class="form-label">{{ $lang->write('Branch') }}</label>
            {!! $dataController->sys_selector('branch', $branches, $branch_id, in_array(auth()->user()->type, ['branch_admin']) ? false : true, $branch_name) !!}
        </div>
        <div style="flex:1 1 160px;min-width:160px;">
            <label class="form-label">{{ $lang->write('Currency') }}</label>
            <select class="form-select currency">
                @foreach ($currencies as $item)
                    <option value="{{$item['code']}}">{{$item['text']}}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1 1 160px;min-width:140px;">
            <label class="form-label">{{ $lang->write('From') }}</label>
            <input type="date" class="form-control date" value="{{ date('Y-m-d') }}">
        </div>
        <div style="flex:1 1 160px;min-width:140px;">
            <label class="form-label">{{ $lang->write('To') }}</label>
            <input type="date" class="form-control date2" value="{{ date('Y-m-d') }}">
        </div>
    </div>

    <div id="printable" style="direction: {{ auth()->user()->lang === 'ar' ? 'rtl' : 'ltr' }};">

        <div class="d-none">
            @if (env('SHOW_COMPANY_DATA_IN_CLIENT_ALL_REPORT'))
                <div style="display:flex;align-items-center;justify-content:space-between;margin-bottom:30px;text-align:{{ auth()->user()->lang === 'ar' ? 'right' : 'left' }};direction:{{ auth()->user()->lang === 'ar' ? 'rtl' : 'ltr' }}">
                    <div style="padding-top:20px;">
                        <div style="text-align: center">
                            <img style="width:150px;" src="{{ asset('images/mataz.png') }}?ver={{ env('VERSION') }}" alt="brand" />
                            <div>{{ $settings['address'] }}</div>
                        </div>
                    </div>
                    <img style="width:100px;margin:0 20px" src="{{ asset('images/logo.png') }}?ver={{ env('VERSION') }}" alt="brand" />
                </div>
            @else
                <img style="width:150px;display:block;margin:auto" class="d-none" src="{{ asset('images/logo.png') }}?ver={{ env('VERSION') }}" alt="brand" />
            @endif
        </div>

        {{-- Opening balance strip — one tile per currency --}}
        <div class="balances_ kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:var(--space-4);">
            <div class="kpi-tile accent" style="padding:var(--space-4);">
                <div class="kpi-label" style="margin-bottom:0;">{{ $lang->write('Opening balance') }}</div>
            </div>
            @foreach ($currencies as $item)
                <div class="kpi-tile" style="padding:var(--space-4);">
                    <div class="kpi-label">{{ $item['text'] ?? strtoupper($item['code']) }}</div>
                    <div class="kpi-value" style="font-size:var(--fs-xl);">0.00 <span class="text-muted" style="font-size:var(--fs-md);">{{ $item['symbol'] }}</span></div>
                </div>
            @endforeach
        </div>

        <div class="main-table mt-2"></div>
    </div>

</div>
@endsection