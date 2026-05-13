@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

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


  $customs_brokers = DB::table('customs_brokers')
    ->where('type', 'export_port_customs_fee')
    ->where('deleted', 'false')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($custom) {
      return [
        'val' => (string) $custom->id,
        'txt' => $custom->name,
      ];
    })
    ->toArray();

  $customs_brokers2 = DB::table('customs_brokers')
    ->where('type', 'import_port_customs_fee')
    ->where('deleted', 'false')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($custom) {
      return [
        'val' => (string) $custom->id,
        'txt' => $custom->name,
      ];
    })
    ->toArray();
@endphp
<div class="modal fade" id="customs" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Customs clearance')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">

        <input type="hidden" data-name="container" class="inp">

        <div class="mb-3">
          <label for="">{{$lang->write('Amount')}}:</label>
          <input type="number" data-name="value" class="form-control inp req money">
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

        <div class="mb-3 ">
          <label for="">{{$lang->write('Type')}} :</label>
          <select class="form-select inp" data-name="pay_for">
            <option value="export_port_customs_fee">{{$lang->write('Export Port Customs Fee')}}</option>
            <option value="import_port_customs_fee">{{$lang->write('Import Port Customs Fee')}}</option>
          </select>
        </div>

        <div class="mb-3 custom_">
          <label for="">{{$lang->write('Custom broker')}} :</label>
          {!! $dataController->sys_selector('custom',$customs_brokers ) !!}
        </div>

        <div class="mb-3 custom_2 d-none">
          <label for="">{{$lang->write('Custom broker')}} :</label>
          {!! $dataController->sys_selector('custom2',$customs_brokers2 ) !!}
        </div>

        <div class="mb-3 ">
          <label for="">{{$lang->write('Pay')}} :</label>
          <select class="form-select inp" data-name="payment">
            <option value="">{{$lang->write('Select')}}</option>
            <option value="pay1">{{$lang->write('Account deduction')}}</option>
            <option value="pay2">{{$lang->write('Cash payment')}}</option>
          </select>
        </div>

        <div class="mb-3 branch_">
          <label for="">{{$lang->write('Treasury')}} :</label>
          {!! $dataController->sys_selector('branch',$branches ) !!}
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('Notes')}} :</label>
          <textarea rows="5" class="form-control inp req" data-name="notes"></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary complete_custom_btn">{{$lang->write('Complete')}}</button>
      </div>
    </div>
  </div>
</div>

