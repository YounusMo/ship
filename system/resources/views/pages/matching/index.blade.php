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
@endphp
@extends('layout')
@section('content')
    <div class="treasury">
        <div class="row d-flex align-items-center">
            <div class="col-lg-4 col-12 mb-2">
                <div class="d-flex align-items-center">
                    <h4 class="h4">{{$lang->write('Matching')}}</h4>
                </div>
            </div>
            <div class="col-lg-8 col-12 mb-2 text-end">
                <div class="d-flex align-items-center justify-content-end">
                    <button class='btn btn-primary print' disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M7 9.5a1.5 1.5 0 1 0 0 3a1.5 1.5 0 0 0 0-3m0 2a.5.5 0 1 1 0-1a.5.5 0 0 1 0 1M19.5 6H18V2.5a.5.5 0 0 0-.5-.5h-11a.5.5 0 0 0-.5.5V6H4.5A2.5 2.5 0 0 0 2 8.5V15a3.003 3.003 0 0 0 3 3h1v3.5a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5V18h1a3.003 3.003 0 0 0 3-3V8.5A2.5 2.5 0 0 0 19.5 6M7 3h10v3H7zm10 18H7v-6h10zm4-6a2.003 2.003 0 0 1-2 2h-1v-2.5a.5.5 0 0 0-.5-.5h-11a.5.5 0 0 0-.5.5V17H5a2.003 2.003 0 0 1-2-2V8.5A1.5 1.5 0 0 1 4.5 7h15A1.5 1.5 0 0 1 21 8.5z" stroke-width="0.5" stroke="currentColor" />
                        </svg>
                        {{$lang->write('Print')}}
                    </button>
                    {{-- <div class="w-50 text-start branch mx-2">
                        <label for="">{{$lang->write('From date')}} :</label>
                        <input type="date" class="form-control from" value="">
                    </div>
                    <div class="w-50 text-start branch mx-2">
                        <label for="">{{$lang->write('To date')}} :</label>
                        <input type="date" class="form-control to" value="">
                    </div> --}}
                </div>
            </div>
        </div>
        
        <div class="main-table mt-2" id="printablex">
            
        </div>
    </div>
@endsection