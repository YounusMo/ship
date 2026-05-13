//---------------------------------------------------------
// reconciliation viewer — read-only, admin-only
// loads clients tab on page load, branches tab on first click,
// re-loads both when the "only_diff" toggle changes.
//---------------------------------------------------------
let page = 1;
let branchesLoaded = false;

function reconciliationPayload(){
    var formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('only_diff', $('#only_diff').is(':checked') ? 'true' : 'false');
    return formData;
}

function loadClients(){
    tableLoader('show', '.main-table-clients');
    let width = $('.content').width() - 5;

    $.ajax({
        url: '/reconciliation/clients?page=' + page,
        type: 'POST',
        contentType: false,
        processData: false,
        data: reconciliationPayload(),
        success: e => {
            tableLoader('hide', '.main-table-clients', e, 100, () => {
                $('.card-body').css('width', width + 'px');
                $('.page-link').on('click', function (ev) {
                    ev.preventDefault();
                    page = $(this).text().trim();
                    loadClients();
                });
            });
        }
    });
}

function loadBranches(){
    tableLoader('show', '.main-table-branches');

    $.ajax({
        url: '/reconciliation/branches',
        type: 'POST',
        contentType: false,
        processData: false,
        data: reconciliationPayload(),
        success: e => {
            tableLoader('hide', '.main-table-branches', e, 100, () => {});
            branchesLoaded = true;
        }
    });
}

$(function(){
    loadClients();

    $('button[data-bs-target="#tab-branches"]').on('shown.bs.tab', function(){
        if (!branchesLoaded) loadBranches();
    });

    $('#only_diff').on('change', function(){
        page = 1;
        loadClients();
        if (branchesLoaded) loadBranches();
    });
});
