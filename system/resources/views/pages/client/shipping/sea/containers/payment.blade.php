@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->shipping_currencies;
@endphp
<div class="modal fade" id="payment" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Payment')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">

        <div class="mb-3 ">
          <label for="">{{$lang->write('Pay')}} :</label>
          <select class="form-select inp" data-name="payment">
            <option value="">{{$lang->write('Select')}}</option>
            <option value="pay1">{{$lang->write('Account deduction')}}</option>
            <option value="pay2">{{$lang->write('Cash payment')}}</option>
          </select>
        </div>

        <div class="mb-3 branch_">
          <label for="">{{$lang->write('Branch')}} :</label>
          {!! $dataController->sys_selector('branch',$branches ) !!}
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary save_pay_btn" onclick="save_pay()">{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>

