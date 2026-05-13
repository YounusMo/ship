@php
    use App\Http\Controllers\dataController;
    use App\Http\Controllers\langController;

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

    $branches = DB::table('branches')
      ->where('deleted', 'false')
      ->whereIn('id', [1,2,3])
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
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('Name')}} : *</label>
            <input type="text" class="form-control inp req" data-name='name' value="{{$get->name}}">
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('Code')}} : *</label>
            <input type="text" class="form-control inp req"  data-name='code' value="{{$get->code}}">
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('E-mail')}} :</label>
            <input type="text" class="form-control inp" data-name='email' value="{{$get->email}}">
          </div>
          <div class="mb-3 col-6">
            <label for="">{{$lang->write('Phone')}} : *</label>
            <input type="text" class="form-control inp req numeric" data-name='phone' value="{{$get->phone}}">
          </div>
          <div class="mb-3 col-12">
            <label for="">{{$lang->write('Type')}} : *</label>
            <select class="form-select inp req" data-name='type'>
              <option value="">{{$lang->write('Select')}}</option>
              <option {{$get->type === 'person'  ? 'selected' : ''}} value="person">{{$lang->write('Person')}}</option>
              <option {{$get->type === 'company' ? 'selected' : ''}} value="company">{{$lang->write('Company')}}</option>
            </select>
          </div>

          
          <div class="mb-3 col-12">
            <label for="">{{$lang->write('Password')}} :*</label>
            <input type="text" class="form-control inp req" data-name='pass_txt' value="" placeholder="{{$lang->write('Leave blank to keep current')}}">
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

