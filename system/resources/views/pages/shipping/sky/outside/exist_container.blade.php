@php
  use App\Http\Controllers\langController;

  $lang = new langController();
  $containers_ = DB::table('containers_sky')
    ->where('canceled', 'false')
    ->where('type', 'full')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($suppliers)use($lang) {
        return [
          'val' => (string) $suppliers->id,
          'txt' => $suppliers->name,
        ];
    })
  ->toArray();
@endphp
<div class="modal fade" id="exist_" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Insert to exist trip')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-12 mb-3 container_selector">
            <label for="">{{$lang->write('Trip')}} :</label>
            {!! $dataController->sys_selector('container',$containers_) !!}
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary exist_container_btn" onclick="insert_exist()">{{$lang->write('Create')}}</button>
      </div>
    </div>
  </div>
</div>

