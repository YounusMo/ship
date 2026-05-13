@extends('layout')
@section('content')
    {{-- <div class="res"></div> --}}

    <script>
        window.location.href = "{{ url('/clients/all') }}";
    </script>
@endsection