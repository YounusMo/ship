@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;
  $ship_from  = $dataController->ship_from;

  $clients = Cache::remember('clients_', env("CACHE"), function () {
    return DB::table('clients')
    ->where('deleted', 'false')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($client) {
        return [
          'val' => (string) $client->id,
          'txt' => $client->code,
        ];
    })
    ->toArray();
  });

  $branches = DB::table('branches')
    ->where('deleted', 'false')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($branch)use($lang) {
      return [
        'val' => (string) $branch->id,
        'txt' => $lang->branch($branch->id),
      ];
    })
    ->toArray();

@endphp
<div class="modal fade" id="new_custom_container" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md" style="min-width: 40% !important;">
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
              {!! $dataController->sys_selector('client_id',$clients) !!}
            </div>
            <div class="col-6 mb-3">
              <label for="">{{$lang->write('Shipping from')}} :</label>
              <select data-name="ship_from" class="form-select inp rep">
                {{-- <option value="">{{$lang->write('Select')}}</option> --}}
                @foreach ($ship_from as $item)
                  <option value="{{$item['val']}}">{{$lang->write($item['txt'])}}</option>
                @endforeach
              </select>
          </div>
          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Container number')}} :</label>
            <input type="text" data-name="number" class="form-control inp req">
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Container name')}} :</label>
            <input type="text" data-name="name" class="form-control inp req">
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Port of Arrival')}} :</label>
            <input type="text" data-name="arrival" class="form-control inp req">
          </div>

          <div class="col-6 mb-3">
            <label for="">{{$lang->write('Container size')}} :</label>
            <select data-name="size"  class='form-select inp req'>
              <option value="">{{$lang->write('Select')}}</option>
              <option value="20">20</option>
              <option value="40">40</option>
              <option value="40HQ">40HQ</option>
              <option value="45HQ">45HQ</option>
            </select>
          </div>

          <div class="col-12">
            <hr>
          </div>

          <div class="col-4 mb-3 supplier">
            <label for="">{{$lang->write('Shipping Line')}} :</label>
            {!! $dataController->sys_selector('supplier',$suppliers) !!}
          </div>

          <div class="col-4 mb-3 ">
            <label for="">{{$lang->write('Payment to supplier')}} :</label>
            <select class="form-select inp" data-name="payment_supplier">
              <option value="">{{$lang->write('Select')}}</option>
              <option value="pay1">{{$lang->write('Latter')}}</option>
              <option value="pay2">{{$lang->write('Now')}}</option>
            </select>
          </div>

          <div class="col-4 mb-3 branch_selector2">
            <label for="">{{$lang->write('Treasury')}} :</label>
            {!! $dataController->sys_selector('branch2',$branches ) !!}
          </div>
          
          <div class="col-4 mb-3">
            <label for="">{{$lang->write('Cost')}} $ :</label>
            <input type="number" data-name="cost" class='form-control inp req money' min="0">
          </div>

          <div class="col-4 mb-3">
            <label for="">{{$lang->write('Price for client')}} $ :</label>
            <input type="number" data-name="client_price" class='form-control inp req money' min="0">
          </div>

          <div class="col-4 mb-3">
            <label for="">{{$lang->write('Commission')}} $ :</label>
            <input type="number" data-name="commission" class='form-control inp req money' min="0">
          </div>

          <div class="col-6 mb-3 ">
            <label for="">{{$lang->write('Payment by the client')}} :</label>
            <select class="form-select inp" data-name="payment">
              <option value="">{{$lang->write('Select')}}</option>
              <option value="pay1">{{$lang->write('Account deduction')}}</option>
              <option value="pay2">{{$lang->write('Cash payment')}}</option>
            </select>
          </div>

          <div class="col-6 mb-3 branch_selector">
            <label for="">{{$lang->write('Treasury')}} :</label>
            {!! $dataController->sys_selector('branch',$branches ) !!}
          </div>


          <div class="col-12 mb-3">
            <label for="">{{$lang->write('Notes')}} :</label>
            <textarea data-name='notes' rows="5" class="form-control inp"></textarea>
          </div>

        </div>
        
      </div>
      <div class="modal-footer">
        <div class="row w-100 gx-0">
          <div class="col-6">
            <div class="profit">0.00 $</div>
          </div>
          <div class="col-6 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
            <button type="button" class="btn btn-primary create_custom_btn" onclick="create_custom_container()">{{$lang->write('Create')}}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

