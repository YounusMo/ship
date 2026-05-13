@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $client = DB::table('clients')->select('name')->where('id',$get->client_id)->first();
  $supplier = DB::table('suppliers')->select('name')->where('id',$get->supplier)->first();
@endphp
<div class="modal fade" id="show_custom_container" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Custom container')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        
        <div class="row">
            <div class="col-6 mb-3 client_selector">
              <label for="">{{$lang->write('Client')}} :</label>
              <input type="text" data-name="ship_from" class='form-control inp req' readonly value='{{$client->name ?? '-'}}'>
            </div>
            <div class="col-6 mb-3">
              <label for="">{{$lang->write('Shipping from')}} :</label>
              <input type="text" data-name="ship_from" class='form-control inp req' readonly value='{{$lang->write(ucfirst($get->ship_from))}}'>
          </div>
           <div class="col-6 mb-3">
            <label for="">{{$lang->write('Container number')}} :</label>
            <input type="text" data-name="number" class="form-control inp req" readonly value='{{$get->number}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Container name')}} :</label>
            <input type="text" data-name="name" class="form-control inp req" readonly value='{{$get->name}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Port of Arrival')}} :</label>
            <input type="text" data-name="arrival" class="form-control inp req" readonly value='{{$get->arrival}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Shipping Line')}} :</label>
            <input type="text" data-name="supplier" class='form-control inp req' readonly value='{{$supplier->name ?? '-'}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Container size')}} :</label>
            <input type="text" data-name="size" class='form-control inp req' readonly value='{{$get->size}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Cost')}} $ :</label>
            <input type="number" data-name="cost" class='form-control inp req' readonly value='{{$get->cost}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Price for client')}} $ :</label>
            <input type="number" data-name="client_price" class='form-control inp req' readonly value='{{$get->client_price}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Commission')}} $ :</label>
            <input type="text" data-name="commission" class='form-control inp req' readonly value='{{$get->commission}}'>
          </div>
          

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Client payment method')}} :</label>
            @php
              $payment_ = $get->custom_container_payment === 'pay1' ? 'Account deduction' : 'Cash payment';
            @endphp
            <input type="text" class='form-control' readonly value='{{$lang->write($payment_)}}'>
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Shipping line payment method')}} :</label>
            @php
              $paymentx_ = $get->payment_supplier === 'pay1' ? 'Latter' : 'Now';
            @endphp
            <input type="text" class='form-control' readonly value='{{$lang->write($paymentx_)}}'>
          </div>

          <div class="col-12 mb-3">
            <label for="">{{$lang->write('Notes')}} :</label>
            <textarea data-name='notes' rows="5" class="form-control inp" readonly>{{$get->notes}}</textarea>
          </div>

          

        </div>
        
      </div>
      <div class="modal-footer">
        <div class="row w-100 gx-0">
          <div class="col-6">
            <div class="">{{$dataController->numberFormat($get->profit)}} $</div>
          </div>
          <div class="col-6 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

