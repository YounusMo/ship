@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

    $lang = new langController();
    $dataController = new dataController();

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
<div class="modal fade" id="new_rec" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('User')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="">{{$lang->write('Code')}} :</label>
          <input type="text" class="form-control inp req" data-name='code'>
        </div>
        <div class="mb-3">
          <label for="">{{$lang->write('Name')}} :</label>
          <input type="text" class="form-control inp req" data-name='name'>
        </div>
        <div class="mb-3">
          <label for="">{{$lang->write('E-mail')}} :</label>
          <input type="text" class="form-control inp req" data-name='email'>
        </div>
        <div class="mb-3">
          <label for="">{{$lang->write('Permission')}} :</label>
          <select data-name="type" id="" class="form-select inp req">
            <option value="admin">{{$lang->write('Admin')}}</option>
            <option value="office_work">{{$lang->write('Office Work')}}</option>
            <option value="branch_admin">{{$lang->write('Branch admin')}}</option>
          </select>
        </div>

        <div class="mb-3 branch_selector d-none">
          <label for="">{{$lang->write('Branch')}} :</label>
          {!! $dataController->sys_selector('branch',$branches) !!}
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('Passowrd')}} :</label>
          <input type="text" class="form-control inp" data-name='pass1'>
        </div>
        <div class="mb-3">
          <label for="">{{$lang->write('Confirm password')}} :</label>
          <input type="text" class="form-control inp" data-name='pass2'>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary lets_create">{{$lang->write('Create')}}</button>
        <button type="button" class="btn btn-primary lets_save d-none">{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>

