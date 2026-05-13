@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;
  $sky_purpose = $dataController->sky_purpose;

  $containers_sea = Cache::remember('containers_sky', env("CACHE"), function () {
    return DB::table('containers_sky')
      ->orderBy('id', 'DESC')
      ->get()
      ->map(function ($container) {
          return [
            'val' => (string) $container->id,
            'txt' => $container->number,
          ];
      })
    ->toArray();
  });

  $branches = DB::table('branches');
  $branches = $branches->where('deleted', 'false');
  if (in_array(auth()->user()->type , ['branch_admin'])) {
      $branches = $branches->where('id', auth()->user()->branch);
  }
  $branches = $branches->orderBy('id', 'DESC');
  $branches = $branches->get();
  $branches = $branches->map(function ($branch)use($lang) {
      return [
        'val' => (string) $branch->id,
        'txt' => $lang->branch($branch->id),
      ];
  })
->toArray();
     
@endphp
<div class="modal fade" id="sea_withdraw" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Trip withdrawal fees')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        
        <input type="hidden" class="inp req" data-name='transaction_number'>
        <input type="hidden" class="inp req" data-name='container'>

        <div class="mb-3">
          <label for="">{{$lang->write('Amount')}} :</label>
          <input type="number" class="form-control inp req money" data-name='value'>
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
          <label for="">{{$lang->write('Treasury')}} :</label>
          {!! $dataController->sys_selector('branch',$branches) !!}
        </div>

        <div class="col-12 mb-3 d-none _supp">
          <label for="">{{$lang->write('Payment to shipping line')}} :</label>
          <select class="form-select inp" data-name="payment_supplier">
            <option value="">{{$lang->write('Select')}}</option>
            <option value="pay1">{{$lang->write('Latter')}}</option>
            <option value="pay2">{{$lang->write('Now')}}</option>
          </select>
        </div>
        
        <div class="mb-3 branch_selector">
          <label for="">{{$lang->write('The purpose of the withdrawal')}} :</label>
          <select class="inp req form-select" data-name="purpose">
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($sky_purpose as $key => $item)
              @if (! in_array($key , ['export_port_customs_fee','import_port_customs_fee']))  
                <option value="{{$key}}">{{$lang->write($item)}}</option>
              @endif
            @endforeach
          </select>
        </div>
        
        <div class="mb-3">
          <label for="">{{$lang->write('Notes')}} :</label>
          <textarea rows="5" class="form-control inp req" data-name="notes"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary sea_withdraw_btn" onclick="complete_sea_withdraw()">{{$lang->write('Complete')}}</button>
      </div>
    </div>
  </div>
</div>

