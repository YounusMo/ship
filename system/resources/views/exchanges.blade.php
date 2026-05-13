@php
  use Illuminate\Support\Facades\DB;
  use App\Http\Controllers\settingsController;
  use App\Http\Controllers\userController;

  $settingsController = new settingsController();
  $settings = $settingsController->get();

  use App\Http\Controllers\langController;

  $lang = new langController();
@endphp
<div class="modal fade" id="exchange" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Confirming the exchange rate')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">

        <div class="mb-3">
          <label for="">{{$lang->write('Euro exchange rate')}} :</label>
          <input type="number" step="any"  data-name="currency_eur" class="form-control inp_exc" value="{{$settings['currency_eur']}}">
        </div>
        
        <div class="mb-3">
          <label for="">{{$lang->write('Dinar exchange rate')}} :</label>
          <input type="number" step="any"  data-name="currency_den" class="form-control inp_exc" value="{{$settings['currency_den']}}">
        </div>
        
        <div class="mb-3">
          <label for="">{{$lang->write('Yuan exchange rate')}} :</label>
          <input type="number" step="any"  data-name="currency_cny" class="form-control inp_exc" value="{{$settings['currency_cny']}}">
        </div>
        
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary" onclick="save_exchange()">{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>

