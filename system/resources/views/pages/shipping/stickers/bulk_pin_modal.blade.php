{{--
    Bulk-print PIN confirmation modal. Included by both sea + sky
    container_data views.

    Inputs:
      $containerTable  — 'containers_sea' | 'containers_sky'
      $containerId     — int
      $lang            — already-instantiated langController

    Behaviour:
      - Submits POST to /shipping/stickers/container/{table}/{id}
      - On 200 (PDF), opens the response in a new browser tab.
      - On 4xx, surfaces the JSON `message` inline.
--}}
<div class="modal fade" id="bulkStickerPinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $lang->write('Confirm bulk sticker print') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    {{ $lang->write('This prints a sticker for every piece of every shipment in this container. Enter the print confirmation PIN to proceed.') }}
                </p>
                <input type="password" id="bulkStickerPin" class="form-control text-center"
                       placeholder="••••" maxlength="8" inputmode="numeric"
                       autocomplete="one-time-code" style="letter-spacing: 6px; font-size: 1.4rem;">
                <div id="bulkStickerPinErr" class="text-danger small mt-2" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $lang->write('Cancel') }}</button>
                <button type="button" id="bulkStickerPinSubmit" class="btn btn-dark">{{ $lang->write('Print all stickers') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var url   = {!! json_encode(url('/shipping/stickers/container/' . $containerTable . '/' . $containerId)) !!};
    var modal = document.getElementById('bulkStickerPinModal');
    var pin   = document.getElementById('bulkStickerPin');
    var err   = document.getElementById('bulkStickerPinErr');
    var btn   = document.getElementById('bulkStickerPinSubmit');

    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function showErr(msg) {
        err.textContent = msg;
        err.style.display = 'block';
    }

    function clearErr() {
        err.textContent = '';
        err.style.display = 'none';
    }

    if (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            clearErr();
            pin.value = '';
            pin.focus();
        });
    }

    pin && pin.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') btn.click();
    });

    btn && btn.addEventListener('click', function () {
        var value = (pin.value || '').trim();
        if (!/^\d{4,8}$/.test(value)) {
            showErr('PIN must be 4-8 digits.');
            return;
        }
        clearErr();
        btn.disabled = true;
        var fd = new FormData();
        fd.append('print_pin', value);
        fd.append('_token', getCsrf());

        fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
        }).then(function (resp) {
            if (!resp.ok) {
                return resp.json().then(function (j) {
                    showErr(j && j.message ? j.message : 'Print failed (HTTP ' + resp.status + ').');
                    btn.disabled = false;
                });
            }
            return resp.blob().then(function (blob) {
                var win = window.open(URL.createObjectURL(blob), '_blank');
                if (!win) showErr('Browser blocked the popup. Allow popups and try again.');
                btn.disabled = false;
                if (window.bootstrap) bootstrap.Modal.getInstance(modal).hide();
            });
        }).catch(function (e) {
            showErr('Network error: ' + e.message);
            btn.disabled = false;
        });
    });
})();
</script>
