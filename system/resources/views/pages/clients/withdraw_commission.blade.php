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
@endphp
<div class="modal fade" id="withdraw_commission" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--color-amber-100, #fef3c7), transparent);">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--color-amber-600, #d97706);color:white;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
          </div>
          <h5 class="modal-title">{{$lang->write('Withdraw commission')}}</h5>
        </div>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" />
          </svg>
        </button>
      </div>
      <div class="modal-body">

        <input type="hidden" class="inp req" data-name='id'>
        <input type="hidden" class="inp req" data-name='transaction_number'>

        <div class="mb-3">
          <label class="form-label">{{$lang->write('Commission')}}</label>
          <input type="number" class="form-control inp req money" data-name='commission' value="0" style="font-size:18px;font-weight:600;font-variant-numeric:tabular-nums;">
        </div>

        <div class="mb-3">
          <label class="form-label">{{$lang->write('Currency')}}</label>
          <select class="form-select inp req" data-name='currency'>
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($currencies as $item)
                <option value="{{$item['code']}}">{{$item['text']}}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3 branch_selector d-none">
          <input type="hidden" class="_total_tras" value="0">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0">{{$lang->write('Treasury')}}</label>
            <small class="total_tras_res text-muted"></small>
          </div>
          {!! $dataController->sys_selector('branch',$branches) !!}
        </div>

        <div class="mb-3">
          <label class="form-label">{{$lang->write('Notes')}}</label>
          <textarea rows="3" class="form-control inp" data-name="notes" placeholder="{{$lang->write('Optional context — only if the purpose above is not enough')}}"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary withdraw_btn" onclick="withdraw_commission()">{{$lang->write('Complete')}}</button>
      </div>
    </div>
  </div>
</div>
