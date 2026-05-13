@php
    use App\Http\Controllers\langController;

    $lang = new langController();

@endphp
<div class="sidebar hide-scrollbar">
    {{-- <strong class="m-3 d-block h5 mb-4 text-black">{{env('APP_NAME')}}</strong> --}}
    <img class="mt-4 d-block mx-auto" style="width:100px" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />
    <ul>

        {{-- <li class="{{$page === 'home'  ? 'active' : ''}}">
            <a href="{{url('/')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.3">
                        <path d="M6.133 21C4.955 21 4 20.02 4 18.81v-8.802c0-.665.295-1.295.8-1.71l5.867-4.818a2.09 2.09 0 0 1 2.666 0l5.866 4.818c.506.415.801 1.045.801 1.71v8.802c0 1.21-.955 2.19-2.133 2.19z" />
                        <path d="M9.5 21v-5.5a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2V21" />
                    </g>
                </svg>
                {{$lang->write('Home')}}
            </a>
        </li> --}}
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

        <li class="{{$page === 'profits'  ? 'active' : ''}} d-nosne">
            <a href="{{url('/profits')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 32 32">
                    <g fill="currentColor">
                        <path d="M15.84 19.345h.07c1.5.04 2.7 1.26 2.7 2.76c0 1.28-.87 2.35-2.05 2.67v1.12c0 .4-.32.72-.72.72s-.72-.32-.72-.72v-1.12a2.77 2.77 0 0 1-2.05-2.67c0-.4.32-.72.72-.72s.72.32.72.72c0 .74.59 1.33 1.32 1.33s1.33-.6 1.33-1.33s-.6-1.33-1.33-1.33h-.07a2.765 2.765 0 0 1-2.69-2.76c0-1.28.87-2.35 2.05-2.67v-1.12c0-.4.32-.72.72-.72s.72.32.72.72v1.12c1.18.32 2.05 1.39 2.05 2.67c0 .4-.32.72-.72.72s-.72-.32-.72-.72c0-.73-.6-1.33-1.33-1.33s-1.33.6-1.33 1.33s.6 1.33 1.33 1.33" />
                        <path d="m10.532 5.1l2.786 3.26l-.301.336C7.283 9.982 3 15.103 3 21.225c0 5.382 4.368 9.75 9.75 9.75h6.17c5.382 0 9.75-4.367 9.75-9.749c.01-6.123-4.273-11.244-10.007-12.53a1.1 1.1 0 0 0-.11-.615l2.37-2.713l.153-.236a1.956 1.956 0 0 0-2.892-2.423l-.843-1a2.02 2.02 0 0 0-3.008-.005l-.883.986a1.96 1.96 0 0 0-2.918 2.41m3.799 1.385l-1.696-1.96a1.98 1.98 0 0 0 2.365-.5l.8-1.038l.888 1.052a1.97 1.97 0 0 0 2.3.513L17.3 6.485zM5 21.225c0-5.988 4.852-10.84 10.84-10.84s10.84 4.852 10.83 10.838v.002a7.753 7.753 0 0 1-7.75 7.75h-6.17A7.753 7.753 0 0 1 5 21.225" />
                    </g>
                </svg>
                {{$lang->write('Revenue')}}
            </a>
        </li>
        <li class="{{$page === 'matching'  ? 'active' : ''}}">
            <a href="{{url('/matching')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                	<g fill="none" stroke="currentColor" stroke-width="2">
                		<path stroke-linecap="round" d="M16 14H8m8-4H8" />
                		<circle cx="12" cy="12" r="10" />
                	</g>
                </svg>
                {{$lang->write('Matching')}}
            </a>
        </li>

        <li class="{{$page === 'old_balance_archive'  ? 'active' : ''}}">
            <a href="{{url('/old_balance_archive')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.25 12.5h3.5m-10-5.25a3.5 3.5 0 0 1 3.5-3.5h9.5a3.5 3.5 0 0 1 3.5 3.5v9.5a3.5 3.5 0 0 1-3.5 3.5h-9.5a3.5 3.5 0 0 1-3.5-3.5zm0 1.5h16.5" />
                </svg>
                {{$lang->write('Old Balance Archive')}}
            </a>
        </li>

        <hr>

        <div class="list_title">{{$lang->write('Data')}}</div> 
        <li class="{{$page === 'branches'  ? 'active' : ''}}">
            <a href="{{url('/branches/all')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 8.25a2.75 2.75 0 1 0 0-5.5a2.75 2.75 0 0 0 0 5.5m0 0v7.5m0-7.5c0 2.9 2.35 5.25 5.25 5.25h2M7 15.75a2.75 2.75 0 1 0 0 5.5a2.75 2.75 0 0 0 0-5.5m7.25-2.25a2.75 2.75 0 1 0 5.5 0a2.75 2.75 0 0 0-5.5 0" />
                </svg>
                {{$lang->write('Branches')}}
            </a>
        </li>

        <li class="{{$page === 'suppliers'  ? 'active' : ''}}">
            <a href="{{url('/suppliers')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6">
                        <path d="M6 6.75a6 6 0 1 0 12 0a6 6 0 0 0-12 0m0 0h12" />
                        <path d="M9.75 6.75c0 1.591.237 3.117.659 4.243c.422 1.125.994 1.757 1.591 1.757s1.169-.632 1.591-1.757s.659-2.652.659-4.243s-.237-3.117-.659-4.243C13.169 1.382 12.597.75 12 .75s-1.169.632-1.591 1.757S9.75 5.16 9.75 6.75M2.349 16.875a2.625 2.625 0 1 0 5.25 0a2.625 2.625 0 0 0-5.25 0M9.2 23.25a4.474 4.474 0 0 0-8.449 0m15.65-6.375a2.625 2.625 0 1 0 5.25 0a2.625 2.625 0 0 0-5.25 0m6.849 6.375a4.473 4.473 0 0 0-8.449 0" />
                    </g>
                </svg>
                {{$lang->write('Shipping Lines')}}
            </a>
        </li>

        <li class="{{$page === 'customs_brokers'  ? 'active' : ''}}">
            <a href="{{url('/customs_brokers')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 32 32">
                    <path fill="currentColor" d="M12 2C8.145 2 5 5.145 5 9c0 2.41 1.23 4.55 3.094 5.813C4.527 16.343 2 19.883 2 24h2c0-4.43 3.57-8 8-8c1.375 0 2.656.36 3.781.969A8 8 0 0 0 14 22c0 4.406 3.594 8 8 8s8-3.594 8-8s-3.594-8-8-8a7.96 7.96 0 0 0-4.688 1.531a10 10 0 0 0-1.406-.719A7.02 7.02 0 0 0 19 9c0-3.855-3.145-7-7-7m0 2c2.773 0 5 2.227 5 5s-2.227 5-5 5s-5-2.227-5-5s2.227-5 5-5m10 12c3.324 0 6 2.676 6 6s-2.676 6-6 6s-6-2.676-6-6s2.676-6 6-6m3.281 3.281L22 22.563l-2.281-2.282l-1.438 1.438l3 3l.719.687l.719-.687l4-4z" />
                </svg>
                {{$lang->write('Customs clearance')}}
            </a>
        </li>

        <hr>

        <div class="list_title">{{$lang->write('System')}}</div> 
        <li class="{{$page === 'users'  ? 'active' : ''}}">
            <a href="{{url('/users')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">-->
                    <path fill="none" stroke="currentColor" stroke-width="1.7" d="M8 11A5 5 0 1 0 8 1a5 5 0 0 0 0 10Zm5.023 2.023C11.772 11.76 10.013 11 8 11c-4 0-7 3-7 7v5h7m2-3.5a2.5 2.5 0 1 0 5.002-.002A2.5 2.5 0 0 0 10 19.5ZM23 15l-3-3l-6 6m3.5-3.5l3 3z" />-->
                </svg>
                {{$lang->write('Users')}}
            </a>
        </li>
         <li class="{{$page === 'settings'  ? 'active' : ''}}">
            <a href="{{url('/settings')}}">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                    <g fill="none" fill-rule="evenodd">
                        <path d="m12.594 23.258l-.012.002l-.071.035l-.02.004l-.014-.004l-.071-.036q-.016-.004-.024.006l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.016-.018m.264-.113l-.014.002l-.184.093l-.01.01l-.003.011l.018.43l.005.012l.008.008l.201.092q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.003-.011l.018-.43l-.003-.012l-.01-.01z" />
                        <path fill="currentColor" d="M16 15c1.306 0 2.418.835 2.83 2H20a1 1 0 1 1 0 2h-1.17a3.001 3.001 0 0 1-5.66 0H4a1 1 0 1 1 0-2h9.17A3 3 0 0 1 16 15m0 2a1 1 0 1 0 0 2a1 1 0 0 0 0-2M8 9a3 3 0 0 1 2.762 1.828l.067.172H20a1 1 0 0 1 .117 1.993L20 13h-9.17a3.001 3.001 0 0 1-5.592.172L5.17 13H4a1 1 0 0 1-.117-1.993L4 11h1.17A3 3 0 0 1 8 9m0 2a1 1 0 1 0 0 2a1 1 0 0 0 0-2m8-8c1.306 0 2.418.835 2.83 2H20a1 1 0 1 1 0 2h-1.17a3.001 3.001 0 0 1-5.66 0H4a1 1 0 0 1 0-2h9.17A3 3 0 0 1 16 3m0 2a1 1 0 1 0 0 2a1 1 0 0 0 0-2" />
                    </g>
                </svg>
                {{$lang->write('Settings')}}
            </a>
        </li>
        
    </ul>
    
</div>