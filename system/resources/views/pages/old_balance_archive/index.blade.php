@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    use Illuminate\Support\Facades\Cache;

    $lang = new langController();
    $dataController = new dataController();

    $currencies = $dataController->currencies;
    if (!in_array(auth()->user()->type , ['admin'])) {
        abort(403, 'Unauthorized');
    }

    $withd_usd = DB::table('clients_transactions')->where('status','approved')->where('currency','usd')
            ->where('calc','false')->sum('value');
    $withd_cny = DB::table('clients_transactions')->where('status','approved')->where('currency','cny')
            ->where('calc','false')->sum('value');
    $withd_eur = DB::table('clients_transactions')->where('status','approved')->where('currency','eur')
            ->where('calc','false')->sum('value');
    $withd_den = DB::table('clients_transactions')->where('status','approved')->where('currency','den')
            ->where('calc','false')->sum('value');
@endphp
@extends('layout')
@section('content')
<div class="treasury">

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $lang->write('Old balance archive') }}</h1>
            <div class="page-subtitle">
                {{ $lang->write('Transactions imported from the legacy ledger, excluded from balance recompute') }}
            </div>
        </div>
        <div class="page-actions">
            <div style="min-width:160px;">
                <select class="form-select currency">
                    <option value="cny">RMB</option>
                    <option value="usd">USD</option>
                    <option value="eur">EUR</option>
                    <option value="den">DEN</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Archive totals --}}
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
        <div class="kpi-tile">
            <div class="kpi-label">USD</div>
            <div class="kpi-value" style="font-size:var(--fs-2xl);">{{ $dataController->numberFormat($withd_usd) }}</div>
            <div class="kpi-sub"><span class="currency-badge usd">USD</span></div>
        </div>
        <div class="kpi-tile">
            <div class="kpi-label">RMB</div>
            <div class="kpi-value" style="font-size:var(--fs-2xl);">{{ $dataController->numberFormat($withd_cny) }}</div>
            <div class="kpi-sub"><span class="currency-badge cny">CNY</span></div>
        </div>
        <div class="kpi-tile">
            <div class="kpi-label">EUR</div>
            <div class="kpi-value" style="font-size:var(--fs-2xl);">{{ $dataController->numberFormat($withd_eur) }}</div>
            <div class="kpi-sub"><span class="currency-badge eur">EUR</span></div>
        </div>
        <div class="kpi-tile">
            <div class="kpi-label">DEN</div>
            <div class="kpi-value" style="font-size:var(--fs-2xl);">{{ $dataController->numberFormat($withd_den) }}</div>
            <div class="kpi-sub"><span class="currency-badge den">LYD</span></div>
        </div>
    </div>

    <div class="main-table mt-2" id="printable"></div>
</div>
@endsection
