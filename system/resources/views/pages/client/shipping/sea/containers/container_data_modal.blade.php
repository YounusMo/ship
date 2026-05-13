@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->shipping_currencies;
@endphp
<div class="modal fade" id="container_data" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Details')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        
        <div class="row">
            <div class="col-4 mb-3">
                <label for="">{{$lang->write('Weight (KG)')}} :</label>
                <input type="number" class="form-control inp req" data-name="kg">
            </div>
            <div class="col-4 mb-3">
                <label for="">{{$lang->write('CBM')}} :</label>
                <input type="number" class="form-control inp req" data-name="cbm">
            </div>
            <div class="col-4 mb-3">
                <label for="">{{$lang->write('Number')}} :</label>
                <input type="number" class="form-control inp req" data-name="number">
            </div>
            <div class="col-3 mb-3">
                <label for="">{{$lang->write('Price')}} :</label>
                <input type="number" class="form-control inp req" data-name="price">
            </div>
            <div class="col-3 mb-3">
                <label for="">{{$lang->write('Additional cost')}} :</label>
                <input type="number" class="form-control inp" data-name="plus">
            </div>
            <div class="col-3 mb-3">
                <label for="">{{$lang->write('New total cost')}} :</label>
                <input type="number" class="form-control inp" data-name="new_price">
            </div>
            <div class="col-3 mb-3">
                <label for="">{{$lang->write('Currency')}} :</label>
                <select class="form-select inp" data-name="currency">
                    @foreach ($currencies as $cur)
                        <option value="{{$cur['code']}}">{{$cur['text']}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary save_data_btn" onclick="save_data()">{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>

