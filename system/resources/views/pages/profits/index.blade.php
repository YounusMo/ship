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
                    <h4 class="h4">{{$lang->write('Profits')}}</h4>
                </div>
            </div>
            <div class="col-lg-8 col-12 mb-2 text-end">
                <div class="d-flex align-items-center justify-content-end">
                    
                    <div class="w-25 text-start branch mx-2">
                        <label for="">{{$lang->write('From date')}} :</label>
                        <input type="date" class="form-control from" value="{{date('Y-m-d')}}">
                    </div>
                    <div class="w-25 text-start branch mx-2">
                        <label for="">{{$lang->write('To date')}} :</label>
                        <input type="date" class="form-control to" value="{{date('Y-m-d')}}">
                    </div>
                    <div class="w-25 text-start pt-3 mx-2">
                        <button class="btn btn-primary print">{{$lang->write('Print')}}</button>
                        <button class="btn btn-secondary print_all">{{$lang->write('Print with details')}}</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="main-table mt-2" id="printable">
            
        </div>
    </div>
@endsection