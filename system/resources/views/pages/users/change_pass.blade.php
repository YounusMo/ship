@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;

  $lang = new langController();
@endphp
<div class="modal fade" id="change_pass" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5">{{$lang->write('Change password')}}</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
      <div class="mb-3">
          <label>{{$lang->write('Password')}} :</label>
          <input type="text" class="form-control pass" required>
      </div>
      <div class="mb-3">
          <label>{{$lang->write('Confirm password')}} :</label>
          <input type="text" class="form-control c_pass" required>
      </div>
      </div>
      <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
      <button type="button" class="btn btn-primary save_pass" role="button" type="submit" >{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>