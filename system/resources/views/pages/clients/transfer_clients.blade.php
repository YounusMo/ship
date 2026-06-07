@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;

  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;
  $purposes   = $dataController->client_client_transfer_purposes;

  $clients = DB::table('clients')
    ->where('deleted', 'false')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($client)use($lang) {
        return [
          'val' => (string) $client->id,
          'txt' => $client->name,
        ];
    })
  ->toArray();
@endphp
<div class="modal fade" id="transfer_clients" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--color-purple-100, #ede9fe), transparent);">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--color-purple-600, #7c3aed);color:white;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M4 20L21 3"></path><path d="M21 16v5h-5"></path><path d="M15 15l6 6"></path><path d="M4 4l5 5"></path></svg>
          </div>
          <h5 class="modal-title">{{$lang->write('Transfer to another client')}}</h5>
        </div>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.6" />
          </svg>
        </button>
      </div>
      <div class="modal-body">

        <input type="hidden" class="inp req" data-name='transaction_number'>
        <input type="hidden" class="inp req" data-name='id'>

        <div class="mb-3">
          <label class="form-label">{{$lang->write('Amount')}}</label>
          <input type="number" class="form-control inp req money" data-name='value' style="font-size:18px;font-weight:600;font-variant-numeric:tabular-nums;">
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

        <div class="mb-3 to_client_div">
          <label class="form-label">{{$lang->write('To client')}}</label>
          {!! $dataController->sys_selector('to_client',$clients) !!}
        </div>

        <div class="mb-3">
          <label class="form-label">{{$lang->write('Purpose')}}</label>
          <select class="form-select inp req" data-name="purpose">
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($purposes as $code => $label)
                <option value="{{ $code }}">{{ $lang->write($label) }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">{{$lang->write('Notes')}}</label>
          <textarea rows="3" class="form-control inp" data-name="notes" placeholder="{{$lang->write('Optional context — only if the purpose above is not enough')}}"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary transfer_client_btn" onclick="transfer_client()">{{$lang->write('Complete')}}</button>
      </div>
    </div>
  </div>
</div>
