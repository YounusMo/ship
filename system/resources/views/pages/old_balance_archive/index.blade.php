@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
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
        <div class="row d-flex align-items-center">
            <div class="col-lg-8 col-12 mb-2">
                <div class="d-flex align-items-center">
                    <h4 class="h4">{{$lang->write('Old balance archive')}}</h4>
                </div>
            </div>
            <div class="col-lg-4 col-12 mb-2 text-end">
                <select class="form-select currency">
                    <option value="cny">RMB</option>
                    <option value="usd">USD</option>
                    <option value="eur">EUR</option>
                    <option value="den">DEN</option>
                </select>
            </div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>USD</th>
                    <th>RMB</th>
                    <th>EUR</th>
                    <th>DEN</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{$dataController->numberFormat($withd_usd)}}</td>
                    <td>{{$dataController->numberFormat($withd_cny)}}</td>
                    <td>{{$dataController->numberFormat($withd_eur)}}</td>
                    <td>{{$dataController->numberFormat($withd_den)}}</td>
                </tr>
            </tbody>
        </table>
        <div class="main-table mt-2" id="printable">
            
        </div>
    </div>
@endsection