@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();
@endphp
@if (count($get) < 1)
    <img src="{{asset('images/empty.png')}}" class="d-block mx-auto" style="width: 500px ; opacity:.5">
    @php
        return
    @endphp    
@endif
<input type="hidden" class="count" value="{{$count}}">
<table class="table">
    <thead>
        <tr>
            <th>{{$lang->write('Trip name')}}</th>
            <th>{{$lang->write('Trip number')}}</th>
            <th>{{$lang->write('Port of Arrival')}}</th>
            {{-- <th>{{$lang->write('Trip size')}}</th> --}}
            <th>{{$lang->write('Type')}}</th>
            <th>{{$lang->write('Status')}}</th>
            <th>{{$lang->write('Notes')}}</th>
            <th>{{$lang->write('Created at')}}</th>
            <th>{{$lang->write('Action')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php

                $allow_cancel = true;
                $show = false;
                

                // يتم التأكد من عدم دفع أي مبالغ نقدية
                // $chk_money_1 = DB::table('store_out_sky')->where('container_id',$item->id)->whereNotNull('payment')->count();
                // $chk_money_2 = DB::table('containers_sky_fees')->where('container_id',$item->id)->count();
                // $chk_money_3 = DB::table('suppliers_transactions')->where('container_id',$item->id)->count();
                // $chk_money_4 = DB::table('customs_brokers_transactions')->where('container_id',$item->id)->count();

                // if($chk_money_1 > 0 ||$chk_money_2 > 0 ||$chk_money_3 > 0 ||$chk_money_4 > 0 ){
                //     $allow_cancel = false;
                // }

                if (in_array(auth()->user()->type , ['admin','office_work',])) {
                    $show = true;
                }else{
                    $chk_clients = DB::table('store_out_sky')->where('container_id',$item->id)->get();

                    foreach ($chk_clients as $key => $x) {
                        $chk_client_branch = DB::table('clients')->select('branch')->where('id',$x->client_id)->first();
                        if($chk_client_branch){
                            if($chk_client_branch->branch == auth()->user()->branch){
                                $show = true;
                            }
                        }
                    }
                }
            @endphp
            @if ($show)
                <tr>
                    <td>{{$item->name}}</td>
                    <td>{{$item->number}}</td>
                    <td>{{$item->arrival}}</td>
                    {{-- <td>{{$item->size}}</td> --}}
                    <td>
                        @switch($item->type)
                            @case('full')
                                {{$lang->write('Shared')}}
                            @break
                            @case('custom')
                                @if ($item->commission)
                                    {{$lang->write('Custom trip with commission')}}
                                @else
                                    {{$lang->write('Custom trip')}}
                                @endif
                            @break
                        @endswitch
                    </td>
                    <td>
                        <select class="form-select status" data-id="{{$item->id}}" {{in_array(auth()->user()->type , ['admin','branch_admin']) ? '' : 'disabled'}}>
                            <option {{$item->status === 'processing' ? 'selected' : ''}} value="processing">{{$lang->write('Processing')}}</option>
                            <option {{$item->status === 'in_way' ? 'selected' : ''}} value="in_way">{{$lang->write('In way')}}</option>
                            <option {{$item->status === 'arrived' ? 'selected' : ''}} value="arrived">{{$lang->write('Arrived')}}</option>
                        </select>
                    </td>
                    <td>{{$item->notes}}</td>
                    <td>{{$item->created_date}} {{$item->created_time}}</td>
                    <td>
                        
                       @if (in_array(auth()->user()->type , ['office_work'])) 
                            @if ($item->type === 'full')
                                <a class="btn btn-primary btn-sm" href="{{url('shipping/sea/container')}}/{{$item->id}}" target='_blank'>{{$lang->write('Show')}}</a>    
                            @endif

                            @if ($item->type === 'custom')
                                <button class="btn btn-primary btn-sm" onclick="showContainer({{$item->id}})">{{$lang->write('Show')}}</button>    
                            @endif
                        @endif
                        @if (in_array(auth()->user()->type , ['admin','branch_admin'])) 
                            @if ($item->canceled === 'false')
                                <button class="btn btn-secondary btn-sm" onclick="showCustoms({{$item->id}})">{{$lang->write('Customs clearance')}}</button>    
                                
                                    <button class="btn btn-sm btn-secondary mx-1 container_sea_withdraw" onclick="container_sea_withdraw({{$item->id}})">
                                        {{$lang->write('Withdrawal fees')}}
                                    </button>
                                    @if ($item->type === 'full')
                                    <a class="btn btn-primary btn-sm" href="{{url('shipping/sky/container')}}/{{$item->id}}" target='_blank'>{{$lang->write('Show')}}</a>    

                                    <button class="btn btn-secondary btn-sm" onclick='showPackingList({{$item->id}} , "{{$lang->write('Packing list') . '_' .$item->number}}")'>{{$lang->write('Packing list')}}</button>    
                                    <button class="btn btn-success btn-sm" onclick="printContainer({{$item->id}})">{{$lang->write('Print')}}</button>    
                                    @endif

                                    <button class="btn btn-secondary btn-sm" onclick="showLink({{$item->id}},'{{$item->link}}')">{{$lang->write('Add tracking link')}}</button>    
                                @if ($item->type === 'custom')
                                    <button class="btn btn-primary btn-sm" onclick="showContainer({{$item->id}})">{{$lang->write('Show')}}</button>    
                                @endif
                            
                            @endif
                            @if ($allow_cancel)
                                <button class="btn btn-danger btn-sm" onclick="cancelContainer({{$item->id}})">{{$lang->write('Cancel')}}</button>    
                            @endif
                        @endif

                    </td>
                </tr>
            @endif
            
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>