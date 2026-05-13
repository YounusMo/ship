@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;
  $purposes   = $dataController->client_transfer_purposes;

@endphp
<div class="modal fade" id="transfer" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Change currency')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        
        <input type="hidden" class="inp req" data-name='id'>
        <input type="hidden" class="inp req" data-name='result'>
        <input type="hidden" class="inp req" data-name='transaction_number'>

        <div class="mb-3">
          <label for="">{{$lang->write('Amount')}} :</label>
          <input type="number" class="form-control inp req money" data-name='value'>
        </div>

        <div class="mb-3 d-flex align-items-center gap-2">
          <div style="width: 93%">
            <label for="">{{$lang->write('Exchange rate')}} :</label>
            <input type="number" class="form-control inp req" data-name='exchange_rate'>
          </div>
          <div style="padding-top: 27px;">
            <input type="hidden" class="switched" value="false">
            <button class="btn btn-sm btn-secondary" title="{{$lang->write('Switch')}}" onclick="switchCur()">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="none" stroke="currentColor" stroke-linecap="square" stroke-width="2" d="M21.448 13c-.5 4.777-4.539 8.5-9.448 8.5A9.5 9.5 0 0 1 3.38 16m-.88 4.5v-5h3M2.552 11C3.052 6.223 7.09 2.5 12 2.5A9.5 9.5 0 0 1 20.62 8m.88-4.5v5h-3" />
              </svg>
            </button>
          </div>
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('From currency')}} :</label>
          <select class="form-select inp req" data-name='from_currency'>
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($currencies as $item)
              <option value="{{$item['code']}}">{{$item['text']}}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('To currency')}} :</label>
          <select class="form-select inp req" data-name='to_currency'>
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($currencies as $item)
                <option value="{{$item['code']}}">{{$item['text']}}</option>
            @endforeach
          </select>
        </div>
        
        <div class="mb-3">
          <label for="">{{$lang->write('Purpose')}} :</label>
          <select class="form-select inp req" data-name="purpose">
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($purposes as $code => $label)
                <option value="{{ $code }}">{{ $lang->write($label) }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('Notes')}} :</label>
          <textarea rows="3" class="form-control notes" data-name="notes" placeholder="{{$lang->write('Optional context — only if the purpose above is not enough')}}"></textarea>
        </div>
        {{-- <div class="mb-3">
          <label for="">{{$lang->write('Result')}} :</label>
          <input type="text" class="form-control result_transfer" disabled>
        </div> --}}
      </div>
      <div class="modal-footer">
        <div class="row w-100 gx-0">
          <div class="col-8">
            <div class="result_transfer" style="white-space: nowrap;"></div>
          </div>
          <div class="col-4 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
            <button type="button" class="btn btn-primary transfer_btn" onclick="transfer()">{{$lang->write('Complete')}}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

