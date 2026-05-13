@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $countries = [
    [
      'val' => 'syria',
      'txt' => 'Syria',
    ],
    [
      'val' => 'libya',
      'txt' => 'Libya',
    ],
    [
      'val' => 'china',
      'txt' => 'China',
    ],
  ];

  $branches = [1,2,3];
  if(auth()->user()->type === 'branch_admin'){
    $branches = [auth()->user()->branch];
  }

  $branches = DB::table('branches')
    ->where('deleted', 'false')
    ->whereIn('id', $branches)
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
<div class="modal fade" id="new" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Create')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="mb-3 col-6 branch_selector_">
            <label for="">{{$lang->write('Branch')}} : *</label>
            {!! $dataController->sys_selector('branch',$branches) !!}
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('Code')}} : *</label>
            <input type="text" class="form-control inp req" data-name='code'>
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('Name')}} : *</label>
            <input type="text" class="form-control inp req" data-name='name'>
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('E-mail')}} :</label>
            <input type="text" class="form-control inp" data-name='email'>
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('Phone')}} : *</label>
            <input type="text" class="form-control inp req numeric" data-name='phone'>
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('Type')}} : *</label>
            <select class="form-select inp req" data-name='type'>
              <option value="">{{$lang->write('Select')}}</option>
              <option value="person">{{$lang->write('Person')}}</option>
              <option value="company">{{$lang->write('Company')}}</option>
            </select>
          </div>

          
          <div class="mb-3 col-12">
            <label for="">{{$lang->write('Password')}} :*</label>
            <input type="text" class="form-control inp req" data-name='pass_txt'>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary create_btn" onclick="create()">{{$lang->write('Create')}}</button>
      </div>
    </div>
  </div>
</div>

