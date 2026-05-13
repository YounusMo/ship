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
            <th>{{$lang->write('Code')}}</th>
            <th>{{$lang->write('E-mail')}}</th>
            <th>{{$lang->write('Type')}}</th>
            <th>{{$lang->write('Branch')}}</th>
            <th>{{$lang->write('Created at')}}</th>
            <th>{{$lang->write('Action')}}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($get as $item)
            @php
                $branch = '-';
                if($item->type === 'branch_admin'){
                    $branch_ = DB::table('branches')->select('name','name_en','name_zh')->where('id',$item->branch)->where('deleted','false')->first();

                    if($branch_){
                        $branch = match (auth()->user()->lang) {
                            'ar' => $branch_->name ?? '-',
                            'en' => $branch_->name_en ?? '-',
                            'zh' => $branch_->name_zh ?? '-',
                            default => $branch_->name_en ?? '-'
                        };
                    }
                }
            @endphp
            <tr>
                <td style="width: 50px !important;max-width: 50px !important;">
                    @if (auth()->user()->id != $item->id)
                        <div class="form-check">
                            <input class="form-check-input chk_item" type="checkbox" value="{{$item->id}}" id="chk_{{ $item->id }}">
                        </div>
                    @endif
                </td>
                <td>{{$item->name}}</td>
                <td>{{$item->code}}</td>
                <td>{{$item->email}}</td>
                <td>{{$lang->write(ucfirst(str_replace(['_'] , ' ',$item->type)))}}</td>
                <td>{{$branch}}</td>
                <td>{{$item->created_date}} {{$item->created_time}}</td>
                <td>
                    <button class="btn-sm btn btn-primary edit" data-id='{{$item->id}}'>{{$lang->write('Edit')}}</button>
                    <button class="btn-sm btn btn-secondary change_pass" data-id='{{$item->id}}'>{{$lang->write('Change password')}}</button>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
