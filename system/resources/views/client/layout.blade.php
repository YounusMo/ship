@php
    use App\Http\Controllers\langController;

    $lang = new langController();

    $lng = 'en';
    $dir  = 'ltr';
    if(Auth::check()){
        $lng = auth()->user()->lang;
        $dir  = auth()->user()->lang === 'ar' ? 'rtl' : 'ltr';
    }

@endphp
<!DOCTYPE html>
<html lang="{{$lng}}" dir="{{$dir}}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}"> 
    
    {{-- Libraries --}}
    @if ($dir === 'rtl')
        <link rel="stylesheet" href="{{asset('libraries/bootstrap/bootstrap.min.rtl.css')}}">
    @else
        <link rel="stylesheet" href="{{asset('libraries/bootstrap/bootstrap.min.css')}}">
    @endif

    <link rel="stylesheet" href="{{asset('libraries/printjs/print.css')}}">

    <link rel="shortcut icon" href="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" type="image/x-icon">

    {{-- Custom --}}
    <link rel="stylesheet" href="{{asset('style/'.$dir.'/style.css')}}">

    <title>{{env('APP_NAME')}}</title>
</head>
<body>

    {{-- <div class="chat_container">
        <div class="icon_">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 21a9 9 0 1 0-9-9c0 1.44.338 2.8.94 4.007c.453.911-.177 2.14-.417 3.037a1.17 1.17 0 0 0 1.433 1.433c.897-.24 2.126-.87 3.037-.416A9 9 0 0 0 12 21" />
            </svg>
        </div>
    </div> --}}

    {{-- Loader --}}
        <div class="loader_sys">
            <svg xmlns="http://www.w3.org/2000/svg" width="33" height="33" viewBox="0 0 24 24">
                <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M12 6.99998C9.1747 6.99987 6.99997 9.24998 7 12C7.00003 14.55 9.02119 17 12 17C14.7712 17 17 14.75 17 12">
                    <animateTransform attributeName="transform" attributeType="XML" dur="560ms" from="0,12,12" repeatCount="indefinite" to="360,12,12" type="rotate" />
                </path>
            </svg>
            <span class="text-white" style="font-size: 14px">{{$lang->write('Please wait')}}</span>
        </div>

        <div class="loader_sys_success">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 15 15">
                <path fill="currentColor" fill-rule="evenodd" d="M0 7.5a7.5 7.5 0 1 1 15 0a7.5 7.5 0 0 1-15 0m7.072 3.21l4.318-5.398l-.78-.624l-3.682 4.601L4.32 7.116l-.64.768z" clip-rule="evenodd" />
            </svg>
            <span class="text-white success_txt" style="font-size: 14px"> </span>
        </div>

        <div class="loader_sys_err">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 512 512">
                <path fill="currentColor" fill-rule="evenodd" d="M256 42.667c117.803 0 213.334 95.53 213.334 213.333S373.803 469.334 256 469.334S42.667 373.803 42.667 256S138.197 42.667 256 42.667m48.918 134.25L256 225.836l-48.917-48.917l-30.165 30.165L225.835 256l-48.917 48.918l30.165 30.165L256 286.166l48.918 48.917l30.165-30.165L286.166 256l48.917-48.917z" />
            </svg>
            <span class="text-white err_txt" style="font-size: 14px"> </span>
        </div>
    {{-- /Loader --}}

    <input type="hidden" class="assets_url" value="{{asset(null)}}">
    <div class="wrapper">
        
        <div class="main">
            @if (Auth::check())
                <input type="hidden" value="{{isset($page) ? $page : ''}}" class="page_name">
                <input type="hidden" value="{{auth()->user()->type}}" class="user_type">
                <input type="hidden" value="{{auth()->user()->id}}" class="user_id">
                @include('components.navbar')
            @endif
            <div class="content">
                <div class="ajax_elements"></div>
                <div id="print_data" class="d-none"></div>
                @yield('content')
            </div>
        </div>
    </div>


    <script src="{{asset('./libraries/jquery/jquery.min.js')}}"></script>
    <script src="{{asset('./libraries/sweetalert/sweetalert.min.js')}}"></script>
    <script src="{{asset('./libraries/bootstrap/bootstrap.min.js')}}"></script>
    <script src="{{asset('./libraries/jquery/lazy.js')}}"></script>
    <script src="{{asset('./libraries/chart/chart.js')}}"></script>
    <script src="{{asset('./libraries/printjs/print.js')}}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.lazy/1.7.11/jquery.lazy.min.js"></script>

    <script src="{{asset('./js/main.js')}}"></script>
    <script src="{{asset('./js/sys_selector.js')}}"></script>
    

    <script src="{{asset('./js')}}/{{$section}}/{{$page}}.js?ver={{env('VERSION')}}"></script>    
    
  
</body>
</html>