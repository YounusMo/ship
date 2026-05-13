@php
    use App\Http\Controllers\langController;

    $lang = new langController();

    $txt = $lang->write('Select');

    if($value){
        foreach ($data as $item) {
            if($item['val'] === $value){
                $txt = $item['txt'];
                break;
            }
        }
    }

    if($display_txt){
        $txt = $display_txt;
    }
    
@endphp
<div class="dropdown sys_selector" data-name='{{$name}}'>
    <button class="form-select text-start" data-bs-toggle="dropdown" aria-expanded="false">
        {{$txt}}
    </button>
    <div class="dropdown-menu p-1 w-100">
        <input type="hidden" data-name="{{$name}}" class="inp req" value="{{$value}}">
        <input type="text" data-for="{{$name}}" class="sm_search form-control p-1" placeholder="{{$lang->write('Search')}}">
        <ul class="pt-1 px-0">
            @if ($all)
                <li data-name="{{$name}}" data-val='' data-txt='{{$lang->write('All')}}'><a class="dropdown-item" href="#">{{$lang->write('All')}}</a></li>
            @endif
            @foreach ($data as $item)  
                <li data-name="{{$name}}" data-val='{{$item['val']}}' data-txt='{{$item['txt']}}'><a class="dropdown-item" href="#">{{$item['txt']}}</a></li>
            @endforeach
        </ul>
    </div>
</div>