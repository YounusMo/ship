@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();
    $currencies = $dataController->currencies;
@endphp
<table class="table">
    <thead>
        <tr>
            <th>{{$lang->write('Client')}}</th>
            <th>{{$lang->write('Amount')}}</th>
            <th>{{$lang->write('Currency')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php
                $client = DB::table('clients')->select('name')->where('id',$item->client_id)->first();
            @endphp
            <tr>
                <td>{{$client->name}}</td>
                <td>{{$dataController->numberFormat($item->value)}}</td>
                <td>{{$dataController->get_cur($item->currency , 'text')}}</td>
            </tr>
        @endforeach
    </tbody>
</table>


<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>