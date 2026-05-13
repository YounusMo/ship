@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

@endphp

<div class="modal fade" id="pending" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="min-width:70%">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Pending transactions')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body p-1">
        <div class="d-flex align-items-start">
          <div style="width:18%">
            <ul class="sidebar_ul">

              <li class="active deposit_li" onclick="pending('deposit')">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <g fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" />
                    <path stroke-linecap="round" d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" />
                  </g>
                </svg>
                {{$lang->write('Deposit')}}
              </li>

              <li class="withdraw_li" onclick="pending('withdraw')">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M3 4.27L4.28 3L21 19.72L19.73 21l-3.67-3.67c-.62.67-1.52 1.22-2.56 1.49V21h-3v-2.18C8.47 18.31 7 16.79 7 15h2c0 1.08 1.37 2 3 2c1.13 0 2.14-.44 2.65-1.08l-2.97-2.97C9.58 12.42 7 11.75 7 9c0-.23 0-.45.07-.66zm7.5.91V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2c-.37 0-.72.05-1.05.13L9.4 5.58z" />
                </svg>
                {{$lang->write('Withdraw')}}
              </li>

              <li class="withdraw_commission_li d-dnone" style="white-space:nowrap" onclick="pending('withdraw_commission')">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M3 4.27L4.28 3L21 19.72L19.73 21l-3.67-3.67c-.62.67-1.52 1.22-2.56 1.49V21h-3v-2.18C8.47 18.31 7 16.79 7 15h2c0 1.08 1.37 2 3 2c1.13 0 2.14-.44 2.65-1.08l-2.97-2.97C9.58 12.42 7 11.75 7 9c0-.23 0-.45.07-.66zm7.5.91V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2c-.37 0-.72.05-1.05.13L9.4 5.58z" />
                </svg>
                {{$lang->write('Withdraw commission')}}
              </li>

              <li class="transfer_li" onclick="pending('transfer')">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                  <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4">
                    <path d="m19 16l5 6l5-6" />
                    <path d="M9 14s7.5-11.5 20.5-7S42 24.5 42 24.5M39 34s-6 11-19.5 7.5S6 24 6 24M42 8v16M6 24v16m12-12h12m-12-6h12m-6 0v12" />
                  </g>
                </svg>
                {{$lang->write('Change currency')}}
              </li>

            </ul>
          </div>
          <div style="width:82%">
            <div class="report_content ms-1" style="overflow: scroll;min-height: 416px;"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
      </div>
    </div>
  </div>
</div>