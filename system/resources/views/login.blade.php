@php
    use App\Http\Controllers\langController;

    $lang = new langController();

@endphp
@extends('layout')
@section('content')
@if (Auth::check())
    <script>
        window.location = '/'
    </script>
@endif
<style>
    .content{
        margin: 0
    }

    .main{
        margin: 0
    }

    .card-body{
        width:62%;
        box-shadow: 3px 5px 10px #00000014;
    }

    @media(max-width:1000px){
        .card-body{
            width:100%;
        }
    }
    
</style>
<div class="card-body mx-auto login_frm" style="background: white;margin-top:10vh;border-radius:20px;overflow:hidden">
    <div class="row">
        <div class="col-lg-5 col-12 d-none d-sm-block" style="position: relative">
            <img src="{{asset('./images/login.webp')}}" style="width:100%;height:70vh">
            <div style="position: absolute;top: 0;color: white;width: 100%;padding-top: 24vh;">
                {{-- <h1 class="text-center">We believe in <br> creativity !</h1> --}}
            </div>
        </div>
        <div class="col-lg-7 col-12 pt-5">
            <form action="{{url('auth/user/login')}}" method="post">
                <img src="{{asset('./images/logo.png')}}" class="d-block mx-auto" style="width:150px">
                @csrf
                <h2 class="text-center">{{$lang->write('Login',$selected_lang)}}</h2>
                <p class="text-center mt-1" style="font-size: 14px">{{$lang->write('Insert your email or code and password for access to your account',$selected_lang)}}</p>

                <div class="w-75 mx-auto mt-5 div_">
                    @if (session()->has('err'))
                        <div class="alert alert-danger py-2">{{session()->get('err')}}</div>
                    @endif
                    
                    <div class="input-group mb-4">
                        <span class="input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 256 256">
                                <path fill="currentColor" d="M230.92 212c-15.23-26.33-38.7-45.21-66.09-54.16a72 72 0 1 0-73.66 0c-27.39 8.94-50.86 27.82-66.09 54.16a8 8 0 1 0 13.85 8c18.84-32.56 52.14-52 89.07-52s70.23 19.44 89.07 52a8 8 0 1 0 13.85-8M72 96a56 56 0 1 1 56 56a56.06 56.06 0 0 1-56-56" stroke-width="6.5" stroke="currentColor" />
                            </svg>
                        </span>
                        <input type="text" class="form-control" name="email" placeholder="{{$lang->write('Email or code',$selected_lang)}}">
                    </div>

                    <div class="input-group mb-4">
                        <span class="input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 20 20">
                                <path fill="currentColor" d="M15 6a1 1 0 1 1-2 0a1 1 0 0 1 2 0m-2.5-4C9.424 2 7 4.424 7 7.5c0 .397.04.796.122 1.175c.058.27-.008.504-.142.638l-4.54 4.54A1.5 1.5 0 0 0 2 14.915V16.5A1.5 1.5 0 0 0 3.5 18h2A1.5 1.5 0 0 0 7 16.5V16h1a1 1 0 0 0 1-1v-1h1a1 1 0 0 0 1-1v-.18c.493.134 1.007.18 1.5.18c3.076 0 5.5-2.424 5.5-5.5S15.576 2 12.5 2M8 7.5C8 4.976 9.976 3 12.5 3S17 4.976 17 7.5S15.024 12 12.5 12c-.66 0-1.273-.095-1.776-.347A.5.5 0 0 0 10 12.1v.9H9a1 1 0 0 0-1 1v1H7a1 1 0 0 0-1 1v.5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1-.5-.5v-1.586a.5.5 0 0 1 .146-.353l4.541-4.541c.432-.432.522-1.044.412-1.556A4.6 4.6 0 0 1 8 7.5" />
                            </svg>
                        </span>
                        <input type="password" class="form-control" name="password" placeholder="{{$lang->write('Password',$selected_lang)}}">
                    </div>

                    <button class="btn btn-primary w-100">{{$lang->write('Login',$selected_lang)}}</button>

                </div>
            </form>
        </div>
    </div>
</div>
@endsection