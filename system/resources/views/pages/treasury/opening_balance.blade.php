@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    $lang = new langController();
    $dataController = new dataController();
    $currencies = $dataController->currencies;
@endphp

<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:var(--space-4);">
    <div class="kpi-tile accent" style="padding:var(--space-4);display:flex;align-items:center;">
        <div class="kpi-label" style="margin-bottom:0;font-size:var(--fs-sm);color:rgba(255,255,255,0.9);">
            {{ $lang->write('Opening balance') }}
        </div>
    </div>
    @foreach ($currencies as $item)
        @php
            $plus = DB::table('treasury_transactions')->where('created_date','<',$date)->where('plus_minus','plus')->where('currency',$item['code']);
            $minus = DB::table('treasury_transactions')->where('created_date','<',$date)->where('plus_minus','minus')->where('currency',$item['code']);
            if(strlen($branch) > 0){
                $plus  = $plus->where('branch',$branch);
                $minus = $minus->where('branch',$branch);
            }
            $plus  = $plus->sum('value');
            $minus = $minus->sum('value');
            $delta = $plus - $minus;
        @endphp
        <div class="kpi-tile" style="padding:var(--space-4);">
            <div class="kpi-label">{{ strtoupper($item['code']) }}</div>
            <div class="kpi-value {{ $delta >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:var(--fs-xl);">
                {{ $dataController->numberFormat($delta) }}
                <span class="text-muted" style="font-size:var(--fs-md);font-weight:500;">{{ $item['symbol'] }}</span>
            </div>
        </div>
    @endforeach
</div>
