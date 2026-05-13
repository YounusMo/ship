@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;
  $ship_from  = $dataController->ship_from;   

  $clients = Cache::remember('clients_', env("CACHE"), function () {
    return DB::table('clients')
    ->where('deleted', 'false')
    ->orderBy('id', 'DESC')
    ->get()
    ->map(function ($client) {
        return [
            'val' => (string) $client->id,
            'txt' => $client->code,
        ];
    })
    ->toArray();
  });

  $name = DB::table('clients')->select(['name'])->where('id',$get->client_id)->first();


  $images = $get->images ? json_decode($get->images) : [];
@endphp
<div class="modal fade" id="edit_reseved" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
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

        <div class="data">
          <div class="row">
            <div class="col-lg-4 mb-3">
              <label for="">{{$lang->write('Client code')}} :</label>
              <input type="text" class="form-control inp req" readonly data-name='client_code' value="{{$get->client_code}}">
            </div>

            <div class="col-lg-4 mb-3">
              <label for="">{{$lang->write('Client name')}} :</label>
              <input type="text" class="form-control client_name req" readonly data-name='client_name' value="{{$name->name}}">
            </div>

            <div class="col-lg-4 mb-3">
              <label for="">{{$lang->write('Company name')}} :</label>
              <input type="text" class="form-control inp req" data-name='company_name' value="{{$get->company_name}}">
            </div>

            <div class="col-lg-4 mb-3">
              <label for="">{{$lang->write('Package type')}} :</label>
              <select data-name='type' class="form-select inp req">
                <option value="">{{$lang->write('Select')}}</option>
                <option {{$get->type === 'bag' ? 'selected' : ''}} value="bag">{{$lang->write('Bag')}}</option>
                <option {{$get->type === 'pallet' ? 'selected' : ''}} value="pallet">{{$lang->write('Pallet')}}</option>
                <option {{$get->type === 'carton' ? 'selected' : ''}} value="carton">{{$lang->write('Carton')}}</option>
                <option {{$get->type === 'furniture' ? 'selected' : ''}} value="furniture">{{$lang->write('Furniture')}}</option>
                <option {{$get->type === 'piece' ? 'selected' : ''}} value="piece">{{$lang->write('Piece')}}</option>
                <option {{$get->type === 'other' ? 'selected' : ''}} value="piece">{{$lang->write('Other')}}</option>
              </select>
            </div>

            <div class="col-lg-4 mb-3">
              <label for="">{{$lang->write('Number')}} :</label>
              <input type="number" class="form-control inp req" data-name='number' value="{{$get->number}}">
            </div>

            <div class="col-lg-4 mb-3">
              <label for="">{{$lang->write('Category')}} :</label>
              <input type="text" class="form-control inp req" data-name='category' value="{{$get->category}}">
            </div>

            <div class="col-lg-3 mb-3">
              <label for="">{{$lang->write('Weight')}} KG :</label>
              <input type="number" class="form-control inp req" data-name='kg' value="{{$get->kg}}">
            </div>

            <div class="col-lg-3 mb-3">
              <label for="">{{$lang->write('Cubic meter')}} CBM :</label>
              <input type="number" class="form-control inp req" data-name='cbm' value="{{$get->cbm}}">
            </div>

            <div class="col-lg-3 mb-3">
              <label for="">{{$lang->write('Receipt')}} :</label>
              <select data-name='receipt' class="form-select inp req">
                <option value="">{{$lang->write('Select')}}</option>
                <option {{$get->receipt === 'with receipt' ? 'selected' : ''}} value="with receipt">{{$lang->write('With receipt')}}</option>
                <option {{$get->receipt === 'without receipt' ? 'selected' : ''}} value="without receipt">{{$lang->write('Without receipt')}}</option>
              </select>
            </div>

            <div class="col-lg-3 mb-3">
              <label for="">{{$lang->write('Brand')}} :</label>
              <select data-name='brand' class="form-select inp req">
                <option value="">{{$lang->write('Select')}}</option>
                <option {{$get->brand === 'yes' ? 'selected' : ''}} value="yes">{{$lang->write('Yes')}}</option>
                <option {{$get->brand === 'no'  ? 'selected' : ''}} value="no">{{$lang->write('No')}}</option>
              </select>
            </div>

            <div class="col-lg-12 mb-3" style="height: 150px;overflow:scroll">
              <label for="">{{$lang->write('Images')}} : <label class="btn btn-sm btn-primary" for="file_">{{$lang->write('Select')}}</label></label>
              <input type="file" multiple accept=".jpg,.png,.jpeg" id="file_" class="d-none">

              <div id="preview" class="mt-3 d-flex align-items-center">

                @foreach ($images as $key => $item)
                  <div style="position: relative" class='main_img mx-2' data-id='{{$item}}'>
                    <button onclick="removeImgEdit('{{$item}}')">
                      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                          <g fill="none" stroke-linejoin="round" stroke-width="4">
                              <path fill="#ff2f2fff" stroke="#000" d="M24 44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4C12.9543 4 4 12.9543 4 24C4 35.0457 12.9543 44 24 44Z" />
                              <path stroke="#fff" stroke-linecap="round" d="M29.6567 18.3432L18.343 29.6569" />
                              <path stroke="#fff" stroke-linecap="round" d="M18.3433 18.3432L29.657 29.6569" />
                          </g>
                      </svg>
                    </button>
                    <img src="{{asset('photos/sky')}}/{{$get->client_id}}/{{$item}}" alt="">
                  </div>  
                @endforeach
              </div>
            </div>

            <div class="col-lg-12 mb-3">
              <label for="">{{$lang->write('Notes')}} :</label>
              <textarea class="form-control inp" data-name="notes" rows="5">{{$get->notes}}</textarea>
            </div>

          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary save_received_btn" onclick="save_received()">{{$lang->write('Save')}}</button>
      </div>
    </div>
  </div>
</div>

