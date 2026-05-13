@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang           = new langController();
    $dataController = new dataController();
@endphp
<div class="modal fade" id="edit" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Edit')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">

          <div class="mb-3 col-12">
            <label for="">{{$lang->write('Name')}} : *</label>
            <input type="text" class="form-control inp req" data-name='name' value="{{$get->name}}">
          </div>
         
          <div class="mb-3 col-12">
            <label for="">{{$lang->write('Type')}} :</label>
            <select class="form-select inp req" data-name="type">
              <option value="">{{$lang->write('Select')}}</option>
              <option {{$get->type === 'export_port_customs_fee' ? 'selected' : ''}} value="export_port_customs_fee">{{$lang->write('Export Port Customs Fee')}}</option>
              <option {{$get->type === 'import_port_customs_fee' ? 'selected' : ''}} value="import_port_customs_fee">{{$lang->write('Import Port Customs Fee')}}</option>
            </select>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary save_btn" onclick="save()">{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>

