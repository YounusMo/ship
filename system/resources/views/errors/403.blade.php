@php
    use App\Http\Controllers\langController;

    $lang = new langController();
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}"> 
    
    {{-- Libraries --}}
     <link rel="stylesheet" href="{{asset('libraries/bootstrap/bootstrap.min.css')}}">

    <link rel="shortcut icon" href="{{asset('images/icon.png')}}?ver={{env('VERSION')}}" type="image/x-icon">

    {{-- Custom --}}
    <link rel="stylesheet" href="{{asset('style/ltr/style.css')}}">
    <link rel="stylesheet" href="{{asset('style/design-tokens.css')}}">

    <title>{{env('APP_NAME')}}</title>
</head>
<body>

    <style>
        body{
            background: white;
            display: flex;
            justify-content: center;
            align-content: center;
            align-items: center
        }

        h3{
            color: #555454
        }

        @media(max-width:800px){
            img{
                width: 80% !important;
            }
        }
    </style>

    <div class="text-center">
        <img class="d-block mx-auto" src="{{asset('images/500.png')}}?ver={{env('VERSION')}}" style="width:400px">
        <h3>{{$lang->write('Error 403')}} | {{$lang->write('Unauthorized')}}</h3>
        @if (Auth::guard('client')->check())
            <a class="btn btn-primary mx-1" href="{{url('/client')}}">{{$lang->write('Go home')}}</a>
        @else
            <a class="btn btn-primary mx-1" href="{{url('./')}}">{{$lang->write('Go home')}}</a>    
        @endif
    </div>

    
    <script src="{{asset('./libraries/jquery/jquery.min.js')}}"></script>
   
    <script src="{{asset('./libraries/bootstrap/bootstrap.min.js')}}"></script>
    <script src="{{asset('./js/main.js')}}"></script>
  
</body>
</html>