@php
    function user_type($type){
        switch ($type) {
            case 'admin':
                return 'Admin';
            break;
        }
    }

    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();

@endphp
<table class="table">
    <thead>
        <tr>
            <th style="width: 50px !important;max-width: 50px !important;">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="chk_all">
                </div>
            </th>
            <th>{{$lang->write('Name')}}</th>
            <th>{{$lang->write('E-mail')}}</th>
            <th>{{$lang->write('Created at')}}</th>
            <th>{{$lang->write('Action')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            <tr>
                <td style="width: 50px !important;max-width: 50px !important;">
                    @if (auth()->user()->id != $item->id)
                        <div class="form-check">
                            <input class="form-check-input chk_item" type="checkbox" value="{{$item->id}}" id="chk_{{ $item->id }}">
                        </div>
                    @endif
                </td>
                <td>{{$item->name}}</td>
                <td>{{$item->email}}</td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>
                    <button class="btn-sm btn btn-primary edit" data-id='{{$item->id}}'>{{$lang->write('Edit')}}</button>
                    <button class="btn-sm btn btn-secondary change_pass" data-id='{{$item->id}}'>{{$lang->write('Change password')}}</button>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
