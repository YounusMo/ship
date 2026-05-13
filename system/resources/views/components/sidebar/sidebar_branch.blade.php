@php
    use App\Http\Controllers\langController;

    $lang = new langController();

@endphp
<div class="sidebar hide-scrollbar">
    {{-- <strong class="m-3 d-block h5 mb-4 text-black">{{env('APP_NAME')}}</strong> --}}
    <img class="mt-4 d-block mx-auto" style="width:100px" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
    <ul>
        <li class="{{$page === 'clients'  ? 'active' : ''}}">
            <a href="{{url('/clients/all')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 256 256">
                    <path fill="currentColor" d="M117.25 157.92a60 60 0 1 0-66.5 0a95.83 95.83 0 0 0-47.22 37.71a8 8 0 1 0 13.4 8.74a80 80 0 0 1 134.14 0a8 8 0 0 0 13.4-8.74a95.83 95.83 0 0 0-47.22-37.71M40 108a44 44 0 1 1 44 44a44.05 44.05 0 0 1-44-44m210.14 98.7a8 8 0 0 1-11.07-2.33A79.83 79.83 0 0 0 172 168a8 8 0 0 1 0-16a44 44 0 1 0-16.34-84.87a8 8 0 1 1-5.94-14.85a60 60 0 0 1 55.53 105.64a95.83 95.83 0 0 1 47.22 37.71a8 8 0 0 1-2.33 11.07" stroke-width="6.5" stroke="currentColor" />
                </svg>
                {{$lang->write('Clients')}}
            </a>
        </li>
       
        
        <hr>

        <div class="list_title">{{$lang->write('Shipping')}}</div> 
        <li class="{{$page === 'sky'  ? 'active' : ''}}">
            <a href="{{url('/shipping/sky')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M10.5 4.5v4.667a.6.6 0 0 1-.282.51l-7.436 4.647a.6.6 0 0 0-.282.508v.9a.6.6 0 0 0 .746.582l6.508-1.628a.6.6 0 0 1 .746.582v2.96a.6.6 0 0 1-.205.451l-2.16 1.89c-.458.402-.097 1.151.502 1.042l3.256-.591a.6.6 0 0 1 .214 0l3.256.591c.599.11.96-.64.502-1.041l-2.16-1.89a.6.6 0 0 1-.205-.452v-2.96a.6.6 0 0 1 .745-.582l6.51 1.628a.6.6 0 0 0 .745-.582v-.9a.6.6 0 0 0-.282-.508l-7.436-4.648a.6.6 0 0 1-.282-.509V4.5a1.5 1.5 0 0 0-3 0" />
                </svg>
                {{$lang->write('Air Freight')}}
            </a>
        </li>

        <li class="{{$page === 'sea'  ? 'active' : ''}}">
            <a href="{{url('/shipping/sea')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.6">
                        <path d="M2 21.193c.685 1.051 1.571 1.051 2.273 0c2.257-3.452 4.407 2.483 6 .04c2.43-3.664 4.178 2.689 6-.04c2.376-3.635 3.857 2.385 5.727.391" />
                        <path stroke-linejoin="round" d="m3.572 17l-1.497-4.354c-.271-.789.228-1.646.958-1.646h17.825c3.094 0-.864 6-2.861 6M18 11l-2.799-3.499A4 4 0 0 0 12.078 6H8a2 2 0 0 0-2 2v3m4-5V3a1 1 0 0 0-1-1H8" />
                    </g>
                </svg>
                {{$lang->write('Sea Freight')}}
            </a>
        </li>

        <hr>


        <div class="list_title">{{$lang->write('Company')}}</div> 
        <li class="{{$page === 'company_accounting'  ? 'active' : ''}}">
            <a href="{{url('/company/accounting')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 32 32">
                    <path fill="currentColor" d="M28 2H16a2 2 0 0 0-2 2v10H4a2 2 0 0 0-2 2v14h28V4a2 2 0 0 0-2-2M9 28v-7h4v7Zm19 0H15v-8a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v8H4V16h12V4h12Z" stroke-width="1" stroke="currentColor" />
                    <path fill="currentColor" d="M18 8h2v2h-2zm6 0h2v2h-2zm-6 6h2v2h-2zm6 0h2v2h-2zm-6 6h2v2h-2zm6 0h2v2h-2z" stroke-width="1" stroke="currentColor" />
                </svg>
                {{$lang->write('Accounting')}}
            </a>
        </li>

        <li class="{{$page === 'treasury'  ? 'active' : ''}}">
            <a href="{{url('/treasury')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7">
                        <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0" />
                        <path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm15 4h.01M6 12h.01" />
                    </g>
                </svg>
                {{$lang->write('Treasury')}}
            </a>
        </li>

        
        
    </ul>
    
</div>