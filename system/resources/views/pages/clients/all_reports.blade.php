@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

@endphp
<div class="modal fade" id="all_reports" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Create a report')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start">
          <div style="width:12%">
            <ul class="sidebar_ul">

              @foreach ($currencies as $key => $item)
                <li class="{{$key == 0 ? 'active' : ''}} d-flex align-items-center" data-cur='{{$item['code']}}' onclick="all_report_currency('{{$item['code']}}')">
                  <span class="">{{$lang->write(strtoupper($item['code']))}}</span>
                </li>
              @endforeach
            </ul>
          </div>
          <div style="width:88%">
            <div class="row w-50 mx-3 mb-3 d-flex" style="display:none">
              <div class="col-6">
                <label for="">{{$lang->write('From')}} :</label>
                <input type="date" class="form-control from">
              </div>
              <div class="col-6">
                <label for="">{{$lang->write('To')}} :</label>
                <input type="date" class="form-control to">
              </div>
            </div>
            <div class="report_content ms-4 hide-scrollbar" id="report_content" style="overflow: scroll"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary print_btn" onclick="printAllReports()">{{$lang->write('Print')}}</button>
      </div>
    </div>
  </div>
</div>



