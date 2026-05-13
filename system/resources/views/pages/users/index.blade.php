@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    if (!in_array(auth()->user()->type , ['admin'])) {
        abort(403, 'Unauthorized');
    }
@endphp
@extends('layout')
@section('content')
@include('pages.users.new_user')
@include('pages.users.change_pass')

<div class="row mb-3">

    <div class="col-lg-6 col-12 d-flex align-items-center">
        <div class="input-group  flex-nowrap">
            <span class="input-group-text">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m21 21l-4.343-4.343m0 0A8 8 0 1 0 5.343 5.343a8 8 0 0 0 11.314 11.314" stroke-width="1" />
                </svg>
            </span>
            <input type="text" class="form-control search" placeholder="{{$lang->write('Press Enter To Search')}}">
        </div>
    </div>

    <div class="col-lg-6 col-12 text-end">
        <button class="btn btn-primary new">{{$lang->write('Create')}}</button>
        <button class="btn btn-danger delete">{{$lang->write('Permanent deletion')}}</button>
    </div>
</div>

<div class="main-table"></div>

@endsection