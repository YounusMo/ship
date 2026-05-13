@php
  use App\Http\Controllers\langController;

  $lang = new langController();

@endphp
<div class="modal fade" id="new_container" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('New trip')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">

          <div class="col-12 mb-3">
            <label for="">{{$lang->write('Trip number')}} :</label>
            <input type="text" data-name="number" class="form-control inp req">
          </div>

          <div class="col-12 mb-3">
            <label for="">{{$lang->write('Trip name')}} :</label>
            <input type="text" data-name="name" class="form-control inp req">
          </div>

          <div class="col-12 mb-3">
            <label for="">{{$lang->write('Arrival Airport')}} :</label>
            <input type="text" data-name="arrival" class="form-control inp req">
          </div>

          {{-- <div class="col-12 mb-3">
            <label for="">{{$lang->write('Packaging type')}} :</label>
            <select data-name="type"  class='form-select inp req'>
              <option value="">{{$lang->write('Select')}}</option>
              <option value="individual">{{$lang->write('Individual')}}</option>
              <option value="shared">{{$lang->write('Shared')}}</option>
            </select>
          </div> --}}

          {{-- <div class="col-12 mb-3">
            <label for="">{{$lang->write('Trip size')}} :</label>
            <select data-name="size"  class='form-select inp req'>
              <option value="">{{$lang->write('Select')}}</option>
              <option value="20">20</option>
              <option value="40">40</option>
              <option value="40HQ">40HQ</option>
            </select>
          </div> --}}

          <div class="col-12 mb-3 supplier">
            <label for="">{{$lang->write('Shipping Line')}} :</label>
            {!! $dataController->sys_selector('supplier',$suppliers) !!}
          </div>


          <div class="col-12 mb-3">
            <label for="">{{$lang->write('Notes')}} :</label>
            <textarea data-name='notes' rows="5" class="form-control inp"></textarea>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary create_container_btn" onclick="create_container()">{{$lang->write('Create')}}</button>
      </div>
    </div>
  </div>
</div>

