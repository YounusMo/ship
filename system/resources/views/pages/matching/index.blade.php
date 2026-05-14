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
@endphp
@extends('layout')
@section('content')
<div class="treasury">

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $lang->write('Matching') }}</h1>
            <div class="page-subtitle">
                {{ $lang->write('Reconcile treasury entries against client-side transactions') }}
            </div>
        </div>
        <div class="page-actions">
            <button class="btn btn-primary print" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                {{ $lang->write('Print') }}
            </button>
        </div>
    </div>

    <div class="main-table" id="printablex"></div>
</div>
@endsection
