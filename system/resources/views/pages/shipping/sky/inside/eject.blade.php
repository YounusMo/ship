@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->shipping_currencies;
  $transaction_number = $dataController->transaction_number('eject',$get->id);
  
@endphp
<div class="modal fade" id="eject" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Eject')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <input type="hidden" class="inp req" data-name='transaction_number' value="{{$transaction_number}}">

          <div class="col-4 mb-3">
            <label for="">{{$lang->write('Number')}} :</label>
            <input type="number" class="form-control req numeric inp" data-name="number" value="{{$get->number}}">
          </div>

          <div class="col-4 mb-3">
            <label for="">{{$lang->write('Cubic meter')}} CBM :</label>
            <input type="number" class="form-control req numeric inp" data-name="cbm" value="{{$get->cbm}}">
          </div>

          <div class="col-4 mb-3">
            <label for="">{{$lang->write('Weight')}} KG :</label>
            <input type="number" class="form-control req numeric inp" data-name="kg" value="{{$get->kg}}">
          </div>

          <div class="col-3 mb-3">
            <label for="">{{$lang->write('Unit')}} :</label>
            <select class="form-select inp" data-name="unit">
              <option value="">{{$lang->write('Select')}}</option>
              <option value="kg">{{$lang->write('Weight')}}</option>
              <option value="cbm">{{$lang->write('CBM')}}</option>
            </select>
          </div>

          <div class="col-3 mb-3">
            <label for="">{{$lang->write('Price')}} :</label>
            <input type="number" class="form-control req numeric inp money" data-name="price" value="0">
          </div>

          <div class="col-3 mb-3">
            <label for="">{{$lang->write('Additional cost')}} :</label>
            <input type="number" class="form-control numeric inp money" data-name="plus" value="0">
          </div>

          <div class="col-3 mb-3">
            <label for="">{{$lang->write('Currency')}} :</label>
            <select class="form-select inp" data-name="currency">
              <option value="">{{$lang->write('Select')}}</option>
              @foreach ($currencies as $item)
                <option value="{{$item['code']}}">{{$item['text']}}</option>
              @endforeach
            </select>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary eject_btn" onclick="eject()">{{$lang->write('Eject')}}</button>
      </div>
    </div>
  </div>
</div>

