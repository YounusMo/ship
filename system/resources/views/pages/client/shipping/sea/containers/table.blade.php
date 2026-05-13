@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();

    $ids = [];

    foreach ($get as $item){
        $chk_ = DB::table('store_out_sea')
            ->select('container_id')
            ->where('client_id',Auth::guard('client')->user()->id)
            ->where('container_id',$item->id)
        ->first();

        if($chk_){
            $ids[] = $chk_->container_id;
        }
    }   

@endphp
@if (count($ids) < 1)
    <img src="{{asset('images/empty.png')}}" class="d-block mx-auto" style="width: 75% ; opacity:.5">
    @php
        return
    @endphp    
@endif
<input type="hidden" class="count" value="{{$count}}">

@foreach ($get as $item)
    @if (in_array($item->id , $ids))
        <div class="card mb-3" style="width: 95%">
            <div class="card-body">
                <div class="mb-2"><strong>{{$lang->write('Container name')}} : </strong> {{$item->name}}</div>
                <div class="mb-2"><strong>{{$lang->write('Container number')}} :</strong> {{$item->number}}</div>
                <div class="mb-2"><strong>{{$lang->write('Container size')}} :</strong> {{$item->size}}</div>
                <div class="mb-2"><strong>{{$lang->write('Port of Arrival')}} : </strong>{{$item->arrival}}</div>
                <div class="mb-2">
                    <strong>{{$lang->write('Type')}} : </strong>
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
                </div>
                <div class="mb-2">
                    <strong>{{$lang->write('Status')}} : </strong>
                    @if (strlen($item->link) > 0)
                        <a href="{{$item->link}}" target="_blank" style="color: #656565;text-decoration:none">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="1.5" d="M12 5H2v17h17V12m-9 2L22 2m-8 0h8v8" />
                            </svg>
                            {{$lang->write('Tracking link')}}
                        </a>
                    @else
                        {{$lang->write(ucfirst(str_replace(['_'],'',$item->status)))}}
                    @endif
                </div>
                <div class="mb-2"><strong>{{$lang->write('Created')}} :</strong> {{$item->created_date}} {{$item->created_time}}</div>
                <div class="mb-2">
                    @if ($item->type === 'full')
                        <a class="btn btn-primary w-100 mt-2" href="{{url('/client/shipping/sea/container')}}/{{$item->id}}" target='_blank'>{{$lang->write('Show')}}</a>    
                    @endif

                    @if ($item->type === 'custom')
                        <button class="btn btn-primary w-100 mt-2" onclick="showContainer({{$item->id}})">{{$lang->write('Show')}}</button>    
                    @endif
                </div>
            </div>
        </div>
    @endif
@endforeach

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>