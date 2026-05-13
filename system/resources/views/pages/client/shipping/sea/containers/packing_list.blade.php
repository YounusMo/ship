@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;
  
    use Illuminate\Support\Facades\Cache;

    $lang           = new langController();
    $dataController = new dataController();

    $get   = DB::table('containers_sky')->where('id',$id)->first();

    $data_ = DB::table('store_out_sky')->where('container_id',$id)->get();

    $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
        return DB::table('clients')
            ->where('deleted', 'false')
            ->select('id', 'name', 'code')
            ->get()
            ->keyBy('id');
    });

    $th_style = "background-color: #ebebeb;color:#838383;border: 1px solid #ebebeb;white-space: nowrap;";
    $td_style = "border: 1px solid #ebebeb;white-space: nowrap;";

@endphp

<div style="padding: 20px;direction:{{auth()->user()->lang === 'ar' ? 'rtl' : 'ltr'}};text-align:center">

    <img style="width:150px;display:block;margin:auto" src="{{asset('images/logo.png')}}?ver={{env('VERSION')}}" alt="brand" />

    <table style="width: 100%" border="1px">
        <thead>
            <th style="{{$th_style}}">{{$lang->write('Container name')}}</th>
            <th style="{{$th_style}}">{{$lang->write('Container number')}}</th>
        </thead>
        <tbody>
            <tr>
                <td style="{{$td_style}}">{{$get->name}}</td>
                <td style="{{$td_style}}">{{$get->number}}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%" border="1px">
        <thead>
            <thead>
            <tr>
                <th style="{{$th_style}}">{{$lang->write('Client code')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Type')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Category')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Number')}}</th>
                <th style="{{$th_style}}">{{$lang->write('Notes')}}</th>
            </tr>
        </thead>
        </thead>
        <tbody>
            @foreach ($data_ as $item)
                @php
                    $data = DB::table('store_sea')->where('out_id',$item->in_id)->first();
                @endphp
                
                <tr>
                    <td style="{{$td_style}}">{{$clients[$item->client_id]->code ?? '-'}}</td>
                    <td style="{{$td_style}}">{{$lang->write(ucfirst($data->type))}}</td>
                    <td style="{{$td_style}}">{{$data->category}}</td>
                    <td style="{{$td_style}}">{{$item->number}}</td>
                    <td style="{{$td_style}}">{{strlen($data->notes) > 0 ? $data->notes : '-'}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>