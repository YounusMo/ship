@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
    use App\Http\Controllers\settingsController;
    use Illuminate\Support\Facades\Cache;

    $settings       = (new settingsController())->get();
    $lang           = new langController();
    $dataController = new dataController();

    $currency_exchange_rates = $dataController->currency_exchange_rates;
    $currencies              = $dataController->currencies;

    $get   = DB::table('containers_sky')->where('id', $id)->first();
    $data_ = DB::table('store_out_sky')->where('container_id', $id)->get();

    $clients = Cache::remember('clients_compant_accounting', env('CACHE'), function () {
        return DB::table('clients')->select('id', 'name', 'code')->get()->keyBy('id');
    });

    $total_costs    = 0;
    $total_cbm      = 0;
    $total_kg       = 0;
    $total_numbers  = 0;
    $total_expenses = 0;

    $hasLogo = settingsController::brandLogoPath();
    $initial = settingsController::brandInitial($settings);
    $isRtl   = (auth()->user()->lang ?? 'en') === 'ar';
@endphp

<input type="hidden" data-name="title" class="client_data" value="{{ $get->number }}">

<div class="print-wrap" style="direction: {{ $isRtl ? 'rtl' : 'ltr' }}; color: #1a2233; font-family: 'Helvetica Neue', Arial, sans-serif; padding: 18px; font-size: 12px;">

    @include('partials.print_header', [
        'settings' => $settings,
        'lang'     => $lang,
        'title'    => $lang->write('Air trip') . ' — ' . $get->name,
        'subtitle' => '#' . $get->number,
    ])

    {{-- Trip info --}}
    @php
        $cellTh = 'background: #fafbfc; color: #5b667a; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; padding: 6px 8px; border: 1px solid #e2e6ee; text-align: '.($isRtl ? 'right' : 'left').';';
        $cellTd = 'padding: 6px 8px; border: 1px solid #e2e6ee; font-size: 12px;';
    @endphp
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        <thead>
            <tr>
                <th style="{{ $cellTh }}">{{ $lang->write('Trip name') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Trip number') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Port of Arrival') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Trip size') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Created at') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="{{ $cellTd }}">{{ $get->name }}</td>
                <td style="{{ $cellTd }}">{{ $get->number }}</td>
                <td style="{{ $cellTd }}">{{ $get->arrival }}</td>
                <td style="{{ $cellTd }}">{{ $get->size }}</td>
                <td style="{{ $cellTd }}">{{ $get->created_date }} {{ $get->created_time }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Shipments --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        <thead>
            <tr>
                <th style="{{ $cellTh }}">{{ $lang->write('Client code') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Client name') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Company') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('From') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Type') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Category') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Unit') }}</th>
                <th style="{{ $cellTh }} text-align: right;">{{ $lang->write('KG') }}</th>
                <th style="{{ $cellTh }} text-align: right;">{{ $lang->write('CBM') }}</th>
                <th style="{{ $cellTh }} text-align: right;">{{ $lang->write('Qty') }}</th>
                <th style="{{ $cellTh }} text-align: right;">{{ $lang->write('Total cost') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Brand') }}</th>
                <th style="{{ $cellTh }}">{{ $lang->write('Notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data_ as $item)
                @php
                    $data  = DB::table('store_sky')->where('id', $item->in_id)->first();

                    $total = 0;
                    if ($item->unit === 'cbm') $total = floatval($item->price) * floatval($item->cbm);
                    if ($item->unit === 'kg')  $total = floatval($item->price) * floatval($item->kg);
                    if ($item->plus > 0)       $total += floatval($item->plus);
                    if ($item->new_price > 0)  $total = floatval($item->new_price);

                    if ($item->currency !== 'usd') {
                        $rate = floatval($currency_exchange_rates[$item->currency] ?? 1);
                        $total_costs += $rate > 0 ? $total / $rate : 0;
                    } else {
                        $total_costs += $total;
                    }

                    // Fix the pre-existing totals bug: these were never
                    // incremented inside the loop, so the totals row always
                    // showed zero.
                    $total_kg      += floatval($item->kg);
                    $total_cbm     += floatval($item->cbm);
                    $total_numbers += intval($item->number);
                @endphp
                <tr>
                    <td style="{{ $cellTd }}">{{ $clients[$item->client_id]->code ?? '-' }}</td>
                    <td style="{{ $cellTd }}">{{ $clients[$item->client_id]->name ?? '-' }}</td>
                    <td style="{{ $cellTd }}">{{ $data->company_name ?? '-' }}</td>
                    <td style="{{ $cellTd }}">{{ $lang->write(ucfirst($data->ship_from ?? '')) }}</td>
                    <td style="{{ $cellTd }}">{{ $lang->write(ucfirst($data->type ?? '')) }}</td>
                    <td style="{{ $cellTd }}">{{ $data->category ?? '' }}</td>
                    <td style="{{ $cellTd }}">{{ $lang->write(ucfirst($item->unit)) }}</td>
                    <td style="{{ $cellTd }} text-align: right;">{{ $item->kg }}</td>
                    <td style="{{ $cellTd }} text-align: right;">{{ $item->cbm }}</td>
                    <td style="{{ $cellTd }} text-align: right;">{{ $item->number }}</td>
                    <td style="{{ $cellTd }} text-align: right; font-weight: 600;">
                        {{ $dataController->numberFormat($total) }} {{ $dataController->get_cur($item->currency, 'symbol') }}
                    </td>
                    <td style="{{ $cellTd }}">{{ $lang->write(ucfirst($data->brand ?? '')) }}</td>
                    <td style="{{ $cellTd }}">{{ strlen($data->notes ?? '') > 0 ? $data->notes : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    @php
        $sumTh = $cellTh;
        $sumTd = $cellTd . 'font-weight: 700;';
    @endphp
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <thead>
            <tr>
                <th style="{{ $sumTh }}">{{ $lang->write('Total weight') }}</th>
                <th style="{{ $sumTh }}">{{ $lang->write('Total CBM') }}</th>
                <th style="{{ $sumTh }}">{{ $lang->write('Total pieces') }}</th>
                <th style="{{ $sumTh }} text-align: right;">{{ $lang->write('Total revenue') }} (USD)</th>
                <th style="{{ $sumTh }} text-align: right;">{{ $lang->write('Total expenses') }} (USD)</th>
                <th style="{{ $sumTh }} text-align: right;">{{ $lang->write('Net profits') }} (USD)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="{{ $sumTd }}">{{ $dataController->numberFormat($total_kg) }} KG</td>
                <td style="{{ $sumTd }}">{{ $dataController->numberFormat($total_cbm) }}</td>
                <td style="{{ $sumTd }}">{{ $total_numbers }}</td>
                <td style="{{ $sumTd }} text-align: right;">{{ $dataController->numberFormat($total_costs) }} $</td>
                <td style="{{ $sumTd }} text-align: right;">{{ $dataController->numberFormat($total_expenses) }} $</td>
                <td style="background: #c9a246; color: #0e2a47; font-weight: 700; padding: 6px 8px; border: 1px solid #c9a246; text-align: right;">
                    {{ $dataController->numberFormat($total_costs - $total_expenses) }} $
                </td>
            </tr>
        </tbody>
    </table>

    <div style="color: #5b667a; font-size: 9px; text-align: center; margin-top: 10px;">
        {{ $lang->write('Generated by') }} {{ $settings['company_name'] ?? '' }} · {{ date('Y-m-d H:i') }}
    </div>
</div>
