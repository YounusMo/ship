@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;
     
@endphp
<div class="modal fade" id="fix_branch" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Branches transfers')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        
        <input type="hidden" class="inp req" data-name='transaction_number'>

        <div class="mb-3">
          <label for="">{{$lang->write('Amount')}} :</label>
          <input type="number" class="form-control inp req" data-name='value'>
        </div>

        <div class="mb-3">
          <label for="">{{$lang->write('Currency')}} :</label>
          <select class="form-select inp req" data-name='currency'>
            <option value="">{{$lang->write('Select')}}</option>
            @foreach ($currencies as $item)
                <option value="{{$item['code']}}">{{$item['text']}}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3 branch_selector">
          <label for="">{{$lang->write('From branch')}} :</label>
          {!! $dataController->sys_selector('from_branch',$branchesX) !!}
        </div>

        <div class="mb-3 branch_selector2">
          <label for="">{{$lang->write('To branch')}} :</label>
          {!! $dataController->sys_selector('to_branch',$branches) !!}
        </div>
        
        <div class="mb-3">
          <label for="">{{$lang->write('Notes')}} :</label>
          <textarea rows="5" class="form-control inp" data-name="notes"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
        <button type="button" class="btn btn-primary fix_branch_btn" onclick="fix_branch()">{{$lang->write('Complete')}}</button>
      </div>
    </div>
  </div>
</div>

