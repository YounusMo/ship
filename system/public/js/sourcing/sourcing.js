let page = 1;
let table = $('.page_name').val().trim();

let showTrash = false;

function load() {
    const formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('search', $('.search').val());
    formData.append('status', $('.status_filter').val());
    if (showTrash) formData.append('trash', 1);

    // Optional pre-filter set by the server when the page was opened
    // via /sourcing?client_id=N (e.g. the "View proformas" button on
    // the clients page).
    if (window.sourcingPrefilter && window.sourcingPrefilter.client_id) {
        formData.append('client_id', window.sourcingPrefilter.client_id);
    }

    tableLoader('show', '.main-table');

    $.ajax({
        url: '/sourcing/load?page=' + page,
        type: 'POST',
        contentType: false,
        processData: false,
        async: true,
        data: formData,
        success: e => {
            try {
                const res = typeof e === 'string' ? JSON.parse(e) : e;
                $('.main-table').html(res.html || '');
                $('.table_counter').text(res.count ?? 0);
            } catch (err) {
                $('.main-table').html('<div class="alert alert-danger">Load failed</div>');
            }
            tableLoader('hide', '.main-table');
        },
        error: () => {
            $('.main-table').html('<div class="alert alert-danger">Load failed</div>');
            tableLoader('hide', '.main-table');
        }
    });
}

function create() {
    const $modal = $('#new');
    const $btn   = $modal.find('.create_btn');

    const payload = {};
    let missing = false;
    $modal.find('.inp').each(function () {
        const name = $(this).data('name');
        const val  = $(this).val();
        if ($(this).hasClass('req') && (val === '' || val === null)) {
            missing = true;
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
        if (name) payload[name] = val;
    });
    if (missing) return;

    const formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    Object.entries(payload).forEach(([k, v]) => formData.append(k, v ?? ''));

    $btn.prop('disabled', true);
    $.ajax({
        url: '/sourcing/create',
        type: 'POST',
        contentType: false,
        processData: false,
        data: formData,
        success: e => {
            $btn.prop('disabled', false);
            try {
                const res = typeof e === 'string' ? JSON.parse(e) : e;
                if (res.type === 'success') {
                    $modal.modal('hide');
                    $modal.find('.inp').val('');
                    if (res.id) {
                        window.location.href = '/sourcing/' + res.id;
                    } else {
                        load();
                    }
                } else {
                    alert(res.message || 'Failed');
                }
            } catch (err) {
                alert('Server error');
            }
        },
        error: xhr => {
            $btn.prop('disabled', false);
            let msg = 'Failed';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            alert(msg);
        }
    });
}

/* ============================================================
 * Show-page handlers (detail view)
 * Active when .sourcing_id is present in the DOM. The detail
 * page and the index share /js/sourcing/sourcing.js because the
 * layout key`s the JS file off `page`, and we want the sidebar
 * to stay highlighted on both — same value.
 * ============================================================ */
function _csrf() { return $('meta[name="csrf-token"]').attr('content'); }

function _postForm(url, data, cb) {
    const fd = new FormData();
    fd.append('_token', _csrf());
    Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
    $.ajax({
        url, type: 'POST', contentType: false, processData: false, data: fd,
        success: e => {
            try { cb(typeof e === 'string' ? JSON.parse(e) : e); }
            catch (err) { alert('Server error'); }
        },
        error: xhr => {
            let msg = 'Failed';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            alert(msg);
        }
    });
}

function showAcceptQuote(quoteId, currency, total) {
    const $m = $('#acceptQuote');
    $m.find('[data-name="quote_id"]').val(quoteId);
    $m.find('[data-name="commission_amount"]').val(total);
    $m.find('[data-name="commission_currency"]').val(currency);
    $m.modal('show');
}

function submitQuote() {
    const $m = $('#addQuote');
    const payload = {};
    let missing = false;
    $m.find('.inp').each(function () {
        const name = $(this).data('name');
        const val  = $(this).val();
        if ($(this).hasClass('req') && (val === '' || val === null)) {
            missing = true; $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
        if (name) payload[name] = val;
    });
    if (missing) return;
    payload.sourcing_request_id = $('.sourcing_id').val();

    _postForm('/sourcing/quotes/add', payload, res => {
        if (res.type === 'success') {
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function submitAcceptQuote() {
    const $m = $('#acceptQuote');
    const payload = {};
    let missing = false;
    $m.find('.inp').each(function () {
        const name = $(this).data('name');
        const val  = $(this).val();
        if ($(this).hasClass('req') && (val === '' || val === null)) {
            missing = true; $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
        if (name) payload[name] = val;
    });
    if (missing) return;

    _postForm('/sourcing/quotes/accept', payload, res => {
        if (res.type === 'success') {
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function cancelRequest(id) {
    if (!confirm('Cancel this sourcing request? Any posted commission will be reversed.')) return;
    _postForm('/sourcing/cancel', { id }, res => {
        if (res.type === 'success' || res.type === 'noop') {
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function markFulfilled(id) {
    _postForm('/sourcing/fulfill', { id }, res => {
        if (res.type === 'success') {
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

/* ============================================================
 * PROFORMA — Phase 1 handlers (items, photos, payment plan, settings)
 * ============================================================ */
function _sourcingId() { return $('.sourcing_id').val(); }

function _fmReset($modal) {
    $modal.find('.inp').each(function(){
        const t = $(this).attr('type');
        if (t === 'hidden') return;
        $(this).val('');
        $(this).removeClass('is-invalid');
    });
}

/* ---- Items ---- */

function submitItem() {
    const $m = $('#addItem');
    const id = $m.find('[data-name="id"]').val();
    const payload = { sourcing_request_id: _sourcingId() };
    let missing = false;
    $m.find('.inp').each(function(){
        const name = $(this).data('name');
        // Checkboxes need state-aware reading; .val() returns the value
        // attribute regardless of whether the box is checked.
        const val = $(this).attr('type') === 'checkbox'
            ? ($(this).is(':checked') ? '1' : '')
            : $(this).val();
        if ($(this).hasClass('req') && (val === '' || val === null)) {
            missing = true; $(this).addClass('is-invalid');
        } else { $(this).removeClass('is-invalid'); }
        if (name) payload[name] = val;
    });
    if (missing) return;

    const url = id ? '/sourcing/items/update' : '/sourcing/items/add';
    if (id) payload.id = id;

    _postForm(url, payload, res => {
        if (res.type === 'success') {
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

/* ---- Versioning (Phase 13) ---- */

function snapshotNow(id) {
    const label = prompt('Optional label for this snapshot (e.g. "before discount round"):', '');
    if (label === null) return; // canceled
    _postForm('/sourcing/' + id + '/snapshot', { label }, res => {
        if (res.type === 'success') {
            alert('Snapshot v' + res.version_no + ' captured.');
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

/* ---- PO linkage (Phase 12) ---- */

let _poSearchTimer = null;
$(document).on('input', '#po_search_input', function () {
    clearTimeout(_poSearchTimer);
    const q = $(this).val();
    _poSearchTimer = setTimeout(() => _loadPOSearch(q), 250);
});
$(document).on('show.bs.modal', '#poLinkModal', function () {
    $('#po_search_input').val('');
    $('#po_link_note').val('');
    $('#po_link_selected_id').val('');
    _loadPOSearch('');
});

function _loadPOSearch(q) {
    const clientId = $('#po_link_client_id').val();
    $.getJSON('/sourcing/po-search?q=' + encodeURIComponent(q || '') + '&client_id=' + clientId, res => {
        if (res.type !== 'success') return;
        const $r = $('#po_search_results').empty();
        if (res.items.length === 0) {
            $r.append('<div class="small text-muted p-3">No matches.</div>');
            return;
        }
        res.items.forEach(po => {
            const isOwnClient = parseInt(po.customer_id) === parseInt(clientId);
            $r.append(`
                <label class="d-flex justify-content-between align-items-start p-2 border-bottom" style="cursor:pointer;">
                    <input type="radio" name="po_link_radio" value="${po.id}" class="me-2 mt-1" onchange="document.getElementById('po_link_selected_id').value=this.value">
                    <div style="flex:1;">
                        <div>
                            <code>${_esc(po.order_number) || '(no number)'}</code>
                            <span class="badge bg-secondary ms-1" style="font-size:10px;">${(po.status || '').replace(/_/g, ' ')}</span>
                            ${isOwnClient ? '<span class="badge bg-info ms-1" style="font-size:10px;">this client</span>' : ''}
                        </div>
                        <div class="small text-muted">${_esc(po.supplier_name) || 'No supplier'}</div>
                        <div class="small text-muted">
                            USD ${po.actual_total_usd || po.estimated_total_usd || '0'}
                            ${po.delivered_at ? ' · delivered ' + po.delivered_at.substring(0,10) : ''}
                        </div>
                    </div>
                </label>
            `);
        });
    });
}

function submitPOLink() {
    const poId = $('#po_link_selected_id').val();
    if (!poId) { alert('Select a PO from the list'); return; }
    _postForm('/sourcing/' + _sourcingId() + '/po-link', {
        po_id: poId,
        note: $('#po_link_note').val(),
    }, res => {
        if (res.type === 'success' || res.type === 'noop') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

function unlinkPO(linkId) {
    if (!confirm('Unlink this PO from the proforma?')) return;
    _postForm('/sourcing/' + _sourcingId() + '/po-unlink', { link_id: linkId }, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

// Item delivery-date inline updates. Both pickers fire the same endpoint,
// passing both fields so the row stays consistent even if the operator
// edits them at different times.
$(document).on('change', '.item-date-picker', function () {
    const itemId = $(this).data('item-id');
    const promised  = $('.item-date-picker[data-item-id="' + itemId + '"][data-field="promised_delivery_date"]').val();
    const confirmed = $('.item-date-picker[data-item-id="' + itemId + '"][data-field="supplier_confirmed_date"]').val();
    _postForm('/sourcing/items/dates', {
        id: itemId,
        promised_delivery_date: promised,
        supplier_confirmed_date: confirmed,
    }, res => {
        if (res.type !== 'success') alert(res.message || 'Failed');
        else window.location.reload();
    });
});

/* ---- Catalog picker (Phase 11) ---- */
let _catalogSearchTimer = null;
$(document).on('input', '#catalog_search', function () {
    clearTimeout(_catalogSearchTimer);
    const q = $(this).val();
    _catalogSearchTimer = setTimeout(() => _loadCatalog(q), 250);
});

function _loadCatalog(q) {
    $.getJSON('/sourcing/catalog?q=' + encodeURIComponent(q || ''), res => {
        if (res.type !== 'success') return;
        const $r = $('#catalog_results').empty();
        if (res.items.length === 0) {
            $r.append('<div class="small text-muted">No matches. Fill the form below to add a new item.</div>');
            return;
        }
        res.items.forEach(it => {
            $r.append(`
                <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                    <div style="flex:1;cursor:pointer;" onclick='_applyCatalogItem(${JSON.stringify(it).replace(/'/g, "&#39;")})'>
                        <strong class="small">${_esc(it.name)}</strong>
                        ${it.code ? '<span class="text-muted small"> · ' + _esc(it.code) + '</span>' : ''}
                        <div class="small text-muted">
                            ${it.default_unit_cost} ${(it.default_unit_cost_currency || 'usd').toUpperCase()}
                            → ${it.default_unit_price}
                            ${it.usage_count > 0 ? ' · used ' + it.usage_count + 'x' : ''}
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick='_applyCatalogItem(${JSON.stringify(it).replace(/'/g, "&#39;")})'>Use</button>
                </div>
            `);
        });
    });
}
function _esc(s) { return $('<div>').text(s || '').html(); }

function _applyCatalogItem(it) {
    const $m = $('#addItem');
    $m.find('[data-name="catalog_id"]').val(it.id);
    $m.find('[data-name="name"]').val(it.name);
    $m.find('[data-name="code"]').val(it.code || '');
    $m.find('[data-name="description"]').val(it.description || '');
    $m.find('[data-name="unit"]').val(it.unit || 'pcs');
    $m.find('[data-name="unit_cost"]').val(it.default_unit_cost);
    $m.find('[data-name="unit_cost_currency"]').val(it.default_unit_cost_currency || 'usd');
    $m.find('[data-name="unit_price_to_client"]').val(it.default_unit_price);
    $m.find('[data-name="weight_kg"]').val(it.default_weight_kg || '');
    $m.find('[data-name="cbm"]').val(it.default_cbm || '');
    // When user picks from catalog, the "save to catalog" checkbox is
    // pointless — uncheck it to avoid creating a duplicate.
    $m.find('#save_to_catalog').prop('checked', false);
}

// Reset the catalog picker every time the modal opens for a NEW item.
$(document).on('show.bs.modal', '#addItem', function () {
    if ($('#addItem').find('[data-name="id"]').val() === '') {
        $('#catalog_search').val('');
        $('#addItem').find('[data-name="catalog_id"]').val('');
        _loadCatalog('');
    }
});

function editItem(item) {
    const $m = $('#addItem');
    _fmReset($m);
    $m.find('[data-name="id"]').val(item.id);
    $m.find('[data-name="name"]').val(item.name);
    $m.find('[data-name="code"]').val(item.code || '');
    $m.find('[data-name="description"]').val(item.description || '');
    $m.find('[data-name="quantity"]').val(item.quantity);
    $m.find('[data-name="unit"]').val(item.unit || 'pcs');
    $m.find('[data-name="unit_cost"]').val(item.unit_cost);
    $m.find('[data-name="unit_cost_currency"]').val(item.unit_cost_currency || 'usd');
    $m.find('[data-name="unit_price_to_client"]').val(item.unit_price_to_client);
    $m.find('[data-name="weight_kg"]').val(item.weight_kg || '');
    $m.find('[data-name="cbm"]').val(item.cbm || '');
    $m.modal('show');
}

function deleteItem(id) {
    if (!confirm('Delete this item? Its photos will be removed too.')) return;
    _postForm('/sourcing/items/delete', { id }, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

// Reset the add modal when it opens via the "Add item" button (no id set).
$(document).on('show.bs.modal', '#addItem', function(){
    if ($('#addItem').find('[data-name="id"]').val() === '') {
        _fmReset($('#addItem'));
        $('#addItem').find('[data-name="unit"]').val('pcs');
    }
});
$(document).on('hidden.bs.modal', '#addItem', function(){
    $('#addItem').find('[data-name="id"]').val('');
});

/* ---- Photos ---- */

function openPhotoUpload(itemId) { _openPhotoModal(itemId, true); }
function openGallery(itemId)     { _openPhotoModal(itemId, false); }

function _openPhotoModal(itemId, allowUpload) {
    $('#photo_item_id').val(itemId);
    const photos = JSON.parse($('#photos_json').text() || '{}');
    const list = photos[itemId] || [];
    const $g = $('#photo_gallery').empty();
    if (list.length === 0) {
        $g.append('<div class="col-12 text-muted">No photos yet.</div>');
    } else {
        list.forEach(p => {
            const url = '/storage/' + p.path;
            $g.append(`
                <div class="col-6 col-md-4">
                    <div class="card">
                        <a href="${url}" target="_blank"><img src="${url}" class="card-img-top" style="height:160px;object-fit:cover;"></a>
                        <div class="card-body p-2 d-flex justify-content-between align-items-center">
                            ${p.is_primary ? '<span class="badge bg-info">Primary</span>' : '<button class="btn btn-sm btn-outline-secondary" onclick="setPrimary('+p.id+')">Set primary</button>'}
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePhoto(${p.id})">Delete</button>
                        </div>
                    </div>
                </div>
            `);
        });
    }
    $('#photoModal').modal('show');
}

function uploadPhotos() {
    const itemId = $('#photo_item_id').val();
    const files = $('#photo_files')[0].files;
    if (!files || !files.length) { alert('Pick at least one image'); return; }

    const fd = new FormData();
    fd.append('_token', _csrf());
    fd.append('item_id', itemId);
    for (let i = 0; i < files.length; i++) fd.append('photos[]', files[i]);

    $.ajax({
        url: '/sourcing/items/photos/upload',
        type: 'POST', contentType: false, processData: false, data: fd,
        success: e => {
            const res = typeof e === 'string' ? JSON.parse(e) : e;
            if (res.type === 'success') window.location.reload();
            else alert(res.message || 'Failed');
        },
        error: xhr => {
            let m = 'Upload failed';
            try { m = JSON.parse(xhr.responseText).message || m; } catch(e){}
            alert(m);
        }
    });
}

function deletePhoto(id) {
    if (!confirm('Remove this photo?')) return;
    _postForm('/sourcing/items/photos/delete', { id }, res => {
        if (res.type === 'success') window.location.reload();
    });
}

function setPrimary(id) {
    _postForm('/sourcing/items/photos/set-primary', { id }, res => {
        if (res.type === 'success') window.location.reload();
    });
}

/* ---- Proforma settings ---- */

function submitProformaSettings() {
    const payload = {
        id: _sourcingId(),
        display_currency:    $('#settings_display_currency').val(),
        commission_mode:     $('#settings_commission_mode').val(),
        commission_amount:   $('#settings_commission_amount').val() || 0,
        commission_currency: $('#settings_commission_currency').val(),
        terms_text:          $('#settings_terms_text').val(),
    };
    _postForm('/sourcing/proforma/settings', payload, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

/* ---- Payment plan ---- */

function generatePaymentPlan() {
    const plan = $('#paymentPlanPicker').val();
    if (!confirm('Regenerate the payment plan? Scheduled (unpaid) rows will be replaced.')) return;
    _postForm('/sourcing/proforma/payment-plan', { id: _sourcingId(), plan }, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

function editPayment(row) {
    $('#paymentEditTitle').text('Edit installment');
    $('#payment_edit_id').val(row.id);
    $('#payment_label').val(row.label);
    $('#payment_amount').val(row.amount);
    $('#payment_currency').val(row.currency);
    $('#payment_percentage').val(row.percentage);
    $('#payment_due_date').val(row.due_date || '');
    $('#payment_notes').val(row.notes || '');
    $('#paymentEdit').modal('show');
}

function submitPayment() {
    const payload = {
        id:         $('#payment_edit_id').val(),
        label:      $('#payment_label').val(),
        amount:     $('#payment_amount').val(),
        currency:   $('#payment_currency').val(),
        percentage: $('#payment_percentage').val() || 0,
        due_date:   $('#payment_due_date').val() || null,
        notes:      $('#payment_notes').val(),
    };
    _postForm('/sourcing/proforma/payments/update', payload, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

function submitAddPayment() {
    const payload = {
        sourcing_request_id: _sourcingId(),
        label:      $('#addpay_label').val(),
        amount:     $('#addpay_amount').val(),
        currency:   $('#addpay_currency').val(),
        percentage: $('#addpay_percentage').val() || 0,
        due_date:   $('#addpay_due_date').val() || null,
    };
    if (!payload.label || !payload.amount) { alert('Label and amount required'); return; }
    _postForm('/sourcing/proforma/payments/add', payload, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

function deletePayment(id) {
    if (!confirm('Delete this installment?')) return;
    _postForm('/sourcing/proforma/payments/delete', { id }, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

/* ============================================================
 * PROFORMA — Phase 2 handlers
 * (send + share link, on-behalf approval, mark installment paid,
 *  copy helpers for share URL and freight payload)
 * ============================================================ */

function sendProforma(id) {
    if (!confirm('Generate a share link and mark this proforma as sent?')) return;
    _postForm('/sourcing/' + id + '/send', {}, res => {
        if (res.type === 'success') {
            alert('Proforma sent.\n\nPublic link:\n' + res.public_url + '\n\nExpires: ' + res.expires_at);
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function approveOnBehalf(id) {
    if (!confirm('Approve this proforma on behalf of the client?\n\nThis is binding — make sure the client actually confirmed (WhatsApp, call, in person).')) return;
    _postForm('/sourcing/' + id + '/approve-on-behalf', {}, res => {
        if (res.type === 'success') {
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function copyShareUrl() {
    const text = $('#share_url_display').text().trim();
    navigator.clipboard.writeText(text)
        .then(() => alert('Link copied:\n' + text))
        .catch(() => prompt('Copy this link:', text));
}

function copyFreightPayload() {
    const txt = $('#freight_payload_json').text().trim();
    navigator.clipboard.writeText(txt)
        .then(() => alert('Freight payload copied to clipboard (JSON).'))
        .catch(() => prompt('Copy this payload:', txt));
}

/* ---- Change requests (Phase 7) ---- */

function respondChangeRequest(id, intent) {
    const $m = $('#respondCRModal');
    $('#cr_response_id').val(id);
    $('#cr_response_status').val(intent); // 'responded' or 'dismissed'
    $('#cr_response_text').val('');
    $('#cr_response_summary').html(
        intent === 'responded'
            ? 'Marking this as <strong>Responded</strong> — you have already made the requested changes (or will).'
            : 'Marking this as <strong>Dismissed</strong> — declined or not actionable.'
    );
    $m.modal('show');
}

function submitCRResponse() {
    const payload = {
        id:       $('#cr_response_id').val(),
        status:   $('#cr_response_status').val(),
        response: $('#cr_response_text').val(),
    };
    _postForm('/sourcing/change-requests/respond', payload, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

function submitMarkup() {
    const pct = parseFloat(document.getElementById('markup_pct').value);
    if (isNaN(pct)) { alert('Enter a number'); return; }
    if (!confirm('Recompute all item prices to (cost × ' + (1 + pct/100).toFixed(2) + ')? This overwrites current prices.')) return;
    _postForm('/sourcing/' + _sourcingId() + '/apply-markup', { markup_percent: pct }, res => {
        if (res.type === 'success') {
            alert('Applied to ' + res.updated + ' item(s).');
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function mintPortalToken(clientId) {
    if (!confirm('Generate (or rotate) the client\'s portal link?\n\nThe URL shows ALL their proformas in one place. If a link already exists, it will stop working.')) return;
    _postForm('/sourcing/clients/' + clientId + '/portal-token', {}, res => {
        if (res.type === 'success') {
            prompt('Client portal link (copy and send to the client):', res.public_url);
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function rotateToken(id) {
    if (!confirm('Rotate this share link?\n\nThe current link will stop working immediately. View tracking resets to zero.')) return;
    _postForm('/sourcing/' + id + '/rotate-token', {}, res => {
        if (res.type === 'success') {
            alert('New link:\n\n' + res.public_url + '\n\nExpires: ' + res.expires_at);
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

/* ---- Container → item sync (Phase 7) ---- */

function syncFromContainer(id) {
    if (!confirm('Pull the linked container\'s status and update item delivery_status accordingly?')) return;
    _postForm('/sourcing/' + id + '/sync-from-container', {}, res => {
        if (res.type === 'success') {
            alert('Synced. ' + (res.updated || 0) + ' item(s) updated.');
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

/* ---- Documents (Phase 6) ---- */

function uploadDocuments() {
    const files = $('#doc_files')[0].files;
    if (!files || !files.length) { alert('Pick at least one file'); return; }
    const fd = new FormData();
    fd.append('_token', _csrf());
    fd.append('sourcing_request_id', _sourcingId());
    fd.append('label', $('#doc_label').val());
    fd.append('visibility', $('#doc_visibility').val());
    for (let i = 0; i < files.length; i++) fd.append('files[]', files[i]);
    $.ajax({
        url: '/sourcing/documents/upload',
        type: 'POST', contentType: false, processData: false, data: fd,
        success: e => {
            const res = typeof e === 'string' ? JSON.parse(e) : e;
            if (res.type === 'success') window.location.reload();
            else alert(res.message || 'Failed');
        },
        error: xhr => {
            let m = 'Upload failed';
            try { m = JSON.parse(xhr.responseText).message || m; } catch (e) {}
            alert(m);
        }
    });
}

function deleteDocument(id) {
    if (!confirm('Delete this document?')) return;
    _postForm('/sourcing/documents/delete', { id }, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

$(document).on('change', '.doc-visibility-picker', function () {
    const id = $(this).data('doc-id');
    const visibility = $(this).val();
    _postForm('/sourcing/documents/visibility', { id, visibility }, res => {
        if (res.type !== 'success') alert(res.message || 'Failed');
    });
});

function cloneProforma(id) {
    if (!confirm('Clone this proforma as a new draft? Client, items and settings will be copied. Payments, share link and approvals will NOT carry over.')) return;
    _postForm('/sourcing/' + id + '/clone', {}, res => {
        if (res.type === 'success') {
            window.location.href = res.redirect;
        } else {
            alert(res.message || 'Failed');
        }
    });
}

function sendReminder(id) {
    if (!confirm('Send a friendly reminder to the client about this proforma?')) return;
    _postForm('/sourcing/' + id + '/reminder', {}, res => {
        if (res.type === 'success') {
            alert('Reminder sent.');
        } else {
            alert(res.message || 'Failed');
        }
    });
}

// Item delivery status — update on change, no submit button.
$(document).on('change', '.item-status-picker', function () {
    const itemId = $(this).data('item-id');
    const newStatus = $(this).val();
    _postForm('/sourcing/items/status', { id: itemId, delivery_status: newStatus }, res => {
        if (res.type !== 'success') {
            alert(res.message || 'Failed');
        } else {
            // Reload to refresh the rolled-up progress bar at the top of the
            // items section. (Cheap; only this page.)
            window.location.reload();
        }
    });
});

function sendEmail() {
    const payload = {
        to:         document.getElementById('email_to').value.trim(),
        cc:         document.getElementById('email_cc').value.trim(),
        subject:    document.getElementById('email_subject').value.trim(),
        body:       document.getElementById('email_body').value,
        attach_pdf: document.getElementById('email_attach_pdf').checked ? 1 : 0,
    };
    if (!payload.to || !payload.subject || !payload.body) { alert('Fill to, subject, and body'); return; }
    _postForm('/sourcing/' + _sourcingId() + '/email', payload, res => {
        if (res.type === 'success') {
            alert('Email sent.');
            $('#emailProforma').modal('hide');
            window.location.reload();
        } else {
            alert(res.message || 'Failed');
        }
    });
}

/* ---- Mark installment paid ---- */

function markPaid(row) {
    $('#markpaid_id').val(row.id);
    $('#markpaid_amount').val(row.amount);
    $('#markpaid_method').val('wallet');
    $('#markpaid_branch').val('');
    $('#markpaid_notes').val('');
    $('#markpaid_summary').html(
        '<strong>#' + row.sequence + '</strong> ' + (row.label || '') +
        ' · ' + row.amount + ' ' + (row.currency || '').toUpperCase() +
        ' · ' + (row.status || '')
    );
    $('#markPaidModal').modal('show');
}

function submitMarkPaid() {
    const payload = {
        id:     $('#markpaid_id').val(),
        method: $('#markpaid_method').val(),
        amount: $('#markpaid_amount').val(),
        branch: $('#markpaid_branch').val() || null,
        notes:  $('#markpaid_notes').val(),
    };
    if (!payload.amount || parseFloat(payload.amount) <= 0) { alert('Enter an amount'); return; }
    if (payload.method === 'cash' && !payload.branch) { alert('Pick a branch for the cash deposit'); return; }
    _postForm('/sourcing/proforma/payments/mark-paid', payload, res => {
        if (res.type === 'success') window.location.reload();
        else alert(res.message || 'Failed');
    });
}

/* ---- Bulk PDF export (Phase 6) ---- */

function _updateBulkPdfButton() {
    const n = $('.proforma-select:checked').length;
    $('.bulk-pdf-btn').toggle(n > 0);
    // Show trash button only when looking at active rows;
    // show restore button only when looking at trashed rows.
    $('.bulk-trash-btn').toggle(n > 0 && !showTrash);
    $('.bulk-restore-btn').toggle(n > 0 && showTrash);
    $('.selected-count').text(n);
}

function _selectedIds() {
    return $('.proforma-select:checked').map(function () { return parseInt($(this).val()); }).get();
}

function bulkTrashSelected() {
    const ids = _selectedIds();
    if (ids.length === 0) return;
    if (!confirm('Move ' + ids.length + ' proforma(s) to trash?')) return;
    const payload = {};
    ids.forEach((id, i) => { payload['ids[' + i + ']'] = id; });
    _postForm('/sourcing/bulk/trash', payload, res => {
        if (res.type === 'success') { alert('Trashed ' + res.count); load(); }
        else alert(res.message || 'Failed');
    });
}

function bulkRestoreSelected() {
    const ids = _selectedIds();
    if (ids.length === 0) return;
    const payload = {};
    ids.forEach((id, i) => { payload['ids[' + i + ']'] = id; });
    _postForm('/sourcing/bulk/restore', payload, res => {
        if (res.type === 'success') { alert('Restored ' + res.count); load(); }
        else alert(res.message || 'Failed');
    });
}

$(document).on('change', '#select_all_proformas', function () {
    const checked = $(this).is(':checked');
    $('.proforma-select').prop('checked', checked);
    _updateBulkPdfButton();
});
$(document).on('change', '.proforma-select', _updateBulkPdfButton);

function exportSelectedPdf() {
    const ids = $('.proforma-select:checked').map(function () { return parseInt($(this).val()); }).get();
    if (ids.length === 0) { alert('Select at least one proforma'); return; }
    if (ids.length > 25) { alert('Max 25 at a time — pick fewer'); return; }

    // Open in a new tab and POST the ids; mPDF Output('I') streams the
    // PDF inline so the browser renders it directly.
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/sourcing/bulk-pdf';
    form.target = '_blank';
    const token = document.createElement('input');
    token.type = 'hidden'; token.name = '_token'; token.value = _csrf();
    form.appendChild(token);
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function trashProforma(id) {
    if (!confirm('Move this proforma to trash? Data is kept; lists hide it.')) return;
    _postForm('/sourcing/' + id + '/trash', {}, res => {
        if (res.type === 'success' || res.type === 'noop') load();
        else alert(res.message || 'Failed');
    });
}

function restoreProforma(id) {
    _postForm('/sourcing/' + id + '/restore', {}, res => {
        if (res.type === 'success') load();
        else alert(res.message || 'Failed');
    });
}

function destroyProforma(id) {
    if (!confirm('Permanently delete this proforma?\n\nThis cannot be undone. Items, photos, documents and audit rows will be removed. Journal entries are kept.')) return;
    _postForm('/sourcing/' + id + '/destroy', {}, res => {
        if (res.type === 'success') load();
        else alert(res.message || 'Failed');
    });
}

$(function () {
    // Detail page does not need the table list — bail out early.
    if ($('.sourcing_id').length) return;

    load();
    $('.search').on('keydown', e => { if (e.key === 'Enter') { page = 1; load(); } });
    $('.status_filter').on('change', () => { page = 1; load(); });
    $(document).on('click', '.toggle-trash', function () {
        showTrash = !showTrash;
        $(this).toggleClass('btn-outline-secondary btn-warning');
        $(this).text(showTrash ? 'Hide trash' : 'Show trash');
        page = 1;
        load();
    });
});
