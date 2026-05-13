@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;
  $purposes   = $dataController->client_deposit_purposes;

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
<div class="modal fade" id="deposit" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Deposit')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        
        <input type="hidden" class="inp req" data-name='id'>
        <input type="hidden" class="inp req" data-name='transaction_number'>

        <div class="mb-3">
          <input type="hidden" class="_client_tras" value="0">
          <div class="row">
            <div class="col-6">
              <label for="">{{$lang->write('Amount')}} :</label>
            </div>
            <div class="col-6 text-end"><small class="total_client_res"></small></div>
          </div>
          <input type="number" class="form-control inp money req" data-name='value'>
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('Commission')}} :</label>
          <input type="number" class="form-control inp money req" data-name='commission'>
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('Currency')}} :</label>
          <select class="form-select inp req" data-name='currency'>
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($currencies as $item)
                <option value="{{$item['code']}}">{{$item['text']}}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3 branch_selector">
          <input type="hidden" class="_total_tras" value="0">
          <div class="row">
            <div class="col-6">
              <label for="">{{$lang->write('Treasury')}} :</label>
            </div>
            <div class="col-6 text-end"><small class="total_tras_res"></small></div>
          </div>
          {!! $dataController->sys_selector('branch',$branches) !!}
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
          <textarea rows="3" class="form-control inp" data-name="notes" placeholder="{{$lang->write('Optional context — only if the purpose above is not enough')}}"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary deposit_btn" onclick="deposit()">{{$lang->write('Complete')}}</button>
      </div>
    </div>
  </div>
</div>

