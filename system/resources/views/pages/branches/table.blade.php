@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

    $dataController = new dataController();
    $currencies = $dataController->currencies;
@endphp
<input type="hidden" class="count" value="{{$count}}">
<table class="table">
    <thead>
        <tr>
            <th style="width: 50px !important;max-width: 50px !important;">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="chk_all">
                </div>
            </th>
            <th>{{$lang->write('Name')}}</th>
            @foreach ($currencies as $item)
                <th>{{$lang->write('Balance')}} {{$item['text']}}</th>
            @endforeach
            <th>{{$lang->write('Created at')}}</th>
            <th>{{$lang->write('Action')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            <tr>
                <td style="width: 50px !important;max-width: 50px !important;">
                    @if (!in_array($item->id , [1,2,3,15]))
                        <div class="form-check">
                            <input class="form-check-input chk_item" type="checkbox" value="{{$item->id}}" id="chk_{{ $item->id }}">
                        </div>
                    @endif
                </td>

                <td>
                    @php
                        switch (auth()->user()->lang) {
                            case 'ar':
                                echo $item->name;
                            break;
                            case 'en':
                                echo $item->name_en;
                            break;
                            case 'zh':
                                echo $item->name_zh;
                            break;
                        }
                    @endphp
                </td>
               
                @foreach ($currencies as $cur)
                    <td>{{ $dataController->numberFormat($item->{'balance_' . $cur['code']}) .' '. $cur['symbol'] }}</td>
                @endforeach
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>
                    @if ($item->deleted === 'false' && !in_array($item->id , [1,2,3,15]))
                        <button class="btn btn-primary btn-sm" onclick="edit({{$item->id}})">{{$lang->write('Edit')}}</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>