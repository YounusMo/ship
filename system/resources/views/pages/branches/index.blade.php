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

    @include('pages.branches.new')

    <div class="row">
        <div class="col-lg-4 col-12 mb-2">
            <div class="d-flex align-items-center">
                <h4 class="h4">{{$lang->write('Branches')}}</h4>
                <span class="table_counter">0</span>
            </div>
        </div>
        <div class="col-lg-8 col-12 mb-2 text-end">
            <div class="d-flex align-items-center justify-content-end">
                <div class="input-group w-50 mx-2">
                    <span class="input-group-text" id="basic-addon1" style="background: #f4f4f4;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                            <g fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="2">
                                <path d="M21 38c9.389 0 17-7.611 17-17S30.389 4 21 4S4 11.611 4 21s7.611 17 17 17Z" />
                                <path stroke-linecap="round" d="M26.657 14.343A7.98 7.98 0 0 0 21 12a7.98 7.98 0 0 0-5.657 2.343m17.879 18.879l8.485 8.485" />
                            </g>
                        </svg>
                    </span>
                    <input type="text" class="form-control search" placeholder="{{$lang->write('Press Enter to search')}}" style="background: #ffffff;"  aria-describedby="basic-addon1">
                </div>
                {{-- <div class="dropdown">
                    <button class="btn btn-secondary mx-2" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M8.857 12.506C6.37 10.646 4.596 8.6 3.627 7.45c-.3-.356-.398-.617-.457-1.076c-.202-1.572-.303-2.358.158-2.866S4.604 3 6.234 3h11.532c1.63 0 2.445 0 2.906.507c.461.508.36 1.294.158 2.866c-.06.459-.158.72-.457 1.076c-.97 1.152-2.747 3.202-5.24 5.065a1.05 1.05 0 0 0-.402.747c-.247 2.731-.475 4.227-.617 4.983c-.229 1.222-1.96 1.957-2.888 2.612c-.552.39-1.222-.074-1.293-.678a196 196 0 0 1-.674-6.917a1.05 1.05 0 0 0-.402-.755" />
                        </svg>
                    </button>
                    <div class="dropdown-menu p-4">

                    </div>
                </div> --}}

                <span class="in_trash d-none">
                    <button class="btn btn-primary show_trash" data-table='{{$page}}'>{{$lang->write('Back')}}</button>
                    <button class="btn btn-success restore" data-table='{{$page}}'>{{$lang->write('Restore')}}</button>
                    <button class="btn btn-danger delete_permanent" data-table='{{$page}}'>{{$lang->write('Permanent deletion')}}</button>
                </span>
                <span class="out_trash">
                    <button class="btn btn-primary new ms-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                            <g fill="none">
                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-linecap="round" stroke-width="1.3" />
                                <path fill="currentColor" fill-rule="evenodd" d="M15.276 16a11 11 0 0 0-4.37-.446c-1.64.162-3.191.686-4.456 1.517c-1.264.832-2.196 1.943-2.648 3.208a.5.5 0 1 0 .941.336C5.11 19.588 5.885 18.64 7 17.907s2.508-1.21 4.005-1.358c.55-.054 1.103-.063 1.649-.028A2 2 0 0 1 14 16z" clip-rule="evenodd" />
                                <path stroke="currentColor" stroke-linecap="round" d="M18 14v8m4-4h-8" stroke-width="1.3" />
                            </g>
                        </svg>
                        {{$lang->write('New branch')}}
                    </button>

                    <div class="btn-group mx-1">
                        <button type="button" class="btn btn-danger delete" data-table='{{$page}}'>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m18 9l-.84 8.398c-.127 1.273-.19 1.909-.48 2.39a2.5 2.5 0 0 1-1.075.973C15.098 21 14.46 21 13.18 21h-2.36c-1.279 0-1.918 0-2.425-.24a2.5 2.5 0 0 1-1.076-.973c-.288-.48-.352-1.116-.48-2.389L6 9m7.5 6.5v-5m-3 5v-5m-6-4h4.615m0 0l.386-2.672c.112-.486.516-.828.98-.828h3.038c.464 0 .867.342.98.828l.386 2.672m-5.77 0h5.77m0 0H19.5" />
                            </svg>
                            {{$lang->write('Delete')}}
                        </button>
                        <button type="button" class="btn btn-danger dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item show_trash" href="#">{{$lang->write('Show Trash')}}</a></li>
                        </ul>
                    </div>
                </span>
                
            </div>
        </div>
    </div>
    <div class="main-table mt-2">
        
    </div>

    
@endsection