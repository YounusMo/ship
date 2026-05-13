@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

@endphp

@foreach ($get as $item)
    @if ($item->type === 'transfer')
        @php
            $cur  = $dataController->get_cur($item->currency , 'symbol');
            $cur2 = $dataController->get_cur($item->to_currency , 'symbol');

            $data = json_decode($item->data);
        @endphp
        <div class="card mb-3" style="width: 95%;background: #f6f6f6;">
            <div class="card-body">
                <div class="mb-2"><strong>{{$lang->write('Transaction number')}} : </strong> {{$item->auto_id}}</div>
                <div class="mb-2"><strong>{{$lang->write('Action')}} : </strong> {{$dataController->get_type($item->type , $item->plus_minus)}}</div>
                <div class="mb-2">
                    <strong>{{$lang->write('Amount')}} : </strong> 
                    @if ($item->to_currency === $currency)
                        <span style="color:red">{{$dataController->numberFormat($item->transfer_value)}} {{$cur2}}</span>
                    @else
                        <span style="color:green">{{$dataController->numberFormat($item->value)}} {{$cur}}</span>
                    @endif
                </div>
                <div class="mb-2">
                    <strong>{{$lang->write('Remaining balance')}} : </strong> 
                    @if ($item->currency === $currency)
                        {{$dataController->numberFormat($data->from)}}
                        {{$cur}}
                    @endif
                    @if ($item->to_currency === $currency)
                        {{$dataController->numberFormat($data->to)}}
                        {{$cur2}}
                    @endif
                </div>
                <div class="mb-2">
                    <strong>{{$lang->write('Currency')}} : </strong> {{$dataController->get_cur($item->currency , 'text')}} / {{$dataController->get_cur($item->to_currency , 'text')}}
                </div>
                <div class="mb-2">
                    <strong>{{$lang->write('Created at')}} : </strong> {{$item->created_date}} {{$item->created_time}}
                </div>
            </div>
        </div>
    @endif
    @if ($item->type !== 'transfer')
        @php
            $cur = $dataController->get_cur($item->currency , 'symbol');
            $data= json_decode($item->data);
        @endphp
        <div class="card mb-3" style="width: 95%;background: #f6f6f6;">
            <div class="card-body">
                <div class="mb-2"><strong>{{$lang->write('Transaction number')}} : </strong> {{$item->auto_id}}</div>
                <div class="mb-2"><strong>{{$lang->write('Action')}} : </strong> {{$dataController->get_type($item->type , $item->plus_minus)}}</div>
                <div class="mb-2">
                    <strong>{{$lang->write('Amount')}} : </strong> 
                    @if ($item->plus_minus === 'minus')
                        <span style="color:red">{{$dataController->numberFormat($item->value)}} {{$cur}}</span>
                    @else
                        <span style="color:green">{{$dataController->numberFormat($item->value)}} {{$cur}}</span>
                    @endif
                </div>
                <div class="mb-2">
                    <strong>{{$lang->write('Remaining balance')}} : </strong> 
                    {{$dataController->numberFormat($item->remaining_balance)}} {{$cur}}
                </div>
                <div class="mb-2">
                    <strong>{{$lang->write('Currency')}} : </strong> {{$dataController->get_cur($item->currency , 'text')}}
                </div>
                <div class="mb-2">
                    <strong>{{$lang->write('Created at')}} : </strong> {{$item->created_date}} {{$item->created_time}}
                </div>
            </div>
        </div>
    @endif
@endforeach


<div class="pagination-sys">
    {{ $get->links('pagination::bootstrap-5') }}
</div>