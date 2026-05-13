@php
  use App\Http\Controllers\dataController;
  use App\Http\Controllers\langController;
  
  use Illuminate\Support\Facades\Cache;

  $lang           = new langController();
  $dataController = new dataController();

  $currencies = $dataController->currencies;

@endphp
<div class="modal fade" id="reports" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="min-width: 80%">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{$lang->write('Reports')}}</h5>
        <button type="button" class="btn-card-close" data-bs-dismiss="modal" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m7 7l10 10M7 17L17 7" stroke-width="1.3" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start">
          <div style="width:18%">
            <ul class="sidebar_ul">

              <li class="active deposit_li" onclick="deposit_report()">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <g fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" />
                    <path stroke-linecap="round" d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" />
                  </g>
                </svg>
                {{$lang->write('Deposit')}}
              </li>

              <li class="withdraw_li" onclick="withdraw_report()">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M3 4.27L4.28 3L21 19.72L19.73 21l-3.67-3.67c-.62.67-1.52 1.22-2.56 1.49V21h-3v-2.18C8.47 18.31 7 16.79 7 15h2c0 1.08 1.37 2 3 2c1.13 0 2.14-.44 2.65-1.08l-2.97-2.97C9.58 12.42 7 11.75 7 9c0-.23 0-.45.07-.66zm7.5.91V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2c-.37 0-.72.05-1.05.13L9.4 5.58z" />
                </svg>
                {{$lang->write('Withdraw')}}
              </li>

              <li class="withdraw_commission_li d-n3one" onclick="withdraw_commission_report()">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M3 4.27L4.28 3L21 19.72L19.73 21l-3.67-3.67c-.62.67-1.52 1.22-2.56 1.49V21h-3v-2.18C8.47 18.31 7 16.79 7 15h2c0 1.08 1.37 2 3 2c1.13 0 2.14-.44 2.65-1.08l-2.97-2.97C9.58 12.42 7 11.75 7 9c0-.23 0-.45.07-.66zm7.5.91V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2c-.37 0-.72.05-1.05.13L9.4 5.58z" />
                </svg>
                {{$lang->write('Withdraw commission')}}
              </li>

              <li class="transfer_li" onclick="transfer_report()">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4">
                        <path d="m19 16l5 6l5-6" />
                        <path d="M9 14s7.5-11.5 20.5-7S42 24.5 42 24.5M39 34s-6 11-19.5 7.5S6 24 6 24M42 8v16M6 24v16m12-12h12m-12-6h12m-6 0v12" />
                    </g>
                </svg>
                {{$lang->write('Change currency')}}
              </li>

              <li class="transfer_clients_li" onclick="transfer_clients_report()">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <g fill="none">
                    <path d="M24 0v24H0V0zM12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035q-.016-.005-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.017-.018m.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01z" />
                    <path fill="currentColor" d="M20 14a1 1 0 0 1 .117 1.993L20 16H6.414l2.293 2.293a1 1 0 0 1-1.32 1.497l-.094-.083l-3.83-3.83c-.665-.664-.239-1.783.663-1.871L4.241 14zm-4.707-9.707a1 1 0 0 1 1.32-.083l.094.083l3.83 3.83c.665.664.239 1.783-.663 1.871l-.115.006H4a1 1 0 0 1-.117-1.993L4 8h13.586l-2.293-2.293a1 1 0 0 1 0-1.414" stroke-width="0.5" stroke="currentColor" />
                  </g>
                </svg>
                {{$lang->write('Transfers')}}
              </li>

              <li class="exp_li" onclick="exp_report()">
                 <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M5.688 19.116q-1.092 0-1.851-.763q-.76-.763-.76-1.853H1.692V5H16.54v3.616h2.307l3.462 4.653V16.5h-1.616q0 1.09-.764 1.853t-1.856.762t-1.85-.762t-.76-1.853H8.307q0 1.096-.764 1.856t-1.856.76m.004-1q.675 0 1.145-.47t.47-1.146t-.47-1.145t-1.145-.47t-1.145.47t-.47 1.145t.47 1.145t1.145.47m-3-2.615h.647q.213-.662.869-1.138t1.484-.478q.79 0 1.466.468q.675.467.888 1.148h7.493V6H2.691zm15.385 2.616q.675 0 1.145-.47q.47-.471.47-1.146t-.47-1.145t-1.145-.47t-1.145.47t-.47 1.145t.47 1.145t1.145.47M16.538 13.5h4.712l-2.942-3.884h-1.77zm-7.422-2.75" />
                </svg>
                {{$lang->write('Shipping')}}
              </li>

            </ul>
          </div>
          <div style="width:82%">
            <div class="report_content ms-4 " style="overflow: scroll;height: 400px;"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$lang->write('Close')}}</button>
      </div>
    </div>
  </div>
</div>



