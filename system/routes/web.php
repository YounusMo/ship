<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\usersController;
use App\Http\Controllers\settingsController;
use App\Http\Controllers\dataController;
use App\Http\Controllers\langController;
use App\Http\Controllers\clientsController;
use App\Http\Controllers\clientController;
use App\Http\Controllers\branchesController;
use App\Http\Controllers\clientsReportsController;
use App\Http\Controllers\companyAccountController;
use App\Http\Controllers\treasuryController;
use App\Http\Controllers\seaController;
use App\Http\Controllers\skyController;
use App\Http\Controllers\suppliersController;
use App\Http\Controllers\analyticsController;
use App\Http\Controllers\customsBrokersController;
use App\Http\Controllers\sourcingController;
use App\Http\Controllers\profitsController;
use App\Http\Controllers\matchingController;
use App\Http\Controllers\oldBalanceArchiveController;
use App\Http\Controllers\auditController;
use App\Http\Controllers\reconciliationController;
use App\Http\Controllers\receiptsController;
use App\Http\Controllers\accountingController;
use App\Http\Controllers\journalController;
use App\Http\Controllers\shipmentStickersController;

// Logout is POST + CSRF so a malicious <img src> or third-party form can't
// force-log-out an authenticated user. The controller handles either guard
// (web or client) so a single route works for both. No middleware: the
// controller no-ops when neither guard is authenticated, and a POST without
// a valid CSRF token is rejected by Laravel's VerifyCsrfToken middleware.
Route::post('/logout', [usersController::class,'logout']);

// Staff password reset — gap #7. Throttle:login mirrors the login flow
// so the reset endpoint can't be used to enumerate accounts via timing.
Route::middleware('throttle:login')->group(function () {
    Route::get('/password/request', [PasswordResetController::class, 'showRequestForm'])
        ->name('password.request');
    Route::post('/password/email', [PasswordResetController::class, 'sendResetLink'])
        ->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])
        ->name('password.update');
});

// Two-factor auth — gap #6. The challenge routes are public (the
// session holds the pending user). The enrollment routes require
// chkAuthAdmin since you're enrolling your own account.
Route::middleware('throttle:login')->group(function () {
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])
        ->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])
        ->name('two-factor.challenge.verify');
});
Route::middleware(['chkAuthAdmin'])->group(function () {
    Route::get('/two-factor/enroll', [TwoFactorController::class, 'showEnrollment'])
        ->name('two-factor.enroll');
    Route::post('/two-factor/enroll', [TwoFactorController::class, 'confirmEnrollment'])
        ->name('two-factor.enroll.confirm');
    // Admin-only: clear another user's 2FA enrollment.
    Route::middleware(['type:admin'])->post('/two-factor/admin/{id}/reset',
        [TwoFactorController::class, 'adminReset'])
        ->name('two-factor.admin.reset')
        ->where('id', '[0-9]+');
});

Route::middleware(['chkAuthClient'])->group(function(){


    Route::prefix('client')->group(function(){
        Route::get('/', function () {
            return view('pages.client.transactions.index',[
                'section' => 'client',
                'page'    => 'transactions'
            ]);
        });

        Route::prefix('transactions')->group(function(){

            Route::get('/', function () {
                return view('pages.client.transactions.index',[
                    'section' => 'client',
                    'page'    => 'transactions'
                ]);
            });

            Route::post('/load_transactions', [clientController::class,'load_transactions']);
            Route::post('/print_reports', [clientController::class,'print_reports']);
        });



    Route::prefix('shipping')->group(function(){

        Route::prefix('sea')->group(function(){

            Route::get('/', function () {
                return view('pages.client.shipping.sea.index',[
                    'section' => 'sea',
                    'page'    => 'sea'
                ]);
            });

            Route::get('/container/{id}', function ($id) {
                return view('pages.client.shipping.sea.containers.container_data',[
                    'section' => 'sea',
                    'page'    => 'sea',
                    'id'      => $id
                ]);
            });

            
            Route::post('/load_containers',[clientController::class,'load_sea_containers']);
            
        });



        Route::prefix('sky')->group(function(){

            Route::get('/', function () {
                return view('pages.client.shipping.sky.index',[
                    'section' => 'sky',
                    'page'    => 'sky'
                ]);
            });

            Route::get('/container/{id}', function ($id) {
                return view('pages.client.shipping.sky.containers.container_data',[
                    'section' => 'sky',
                    'page'    => 'sky',
                    'id'      => $id
                ]);
            });

            Route::post('/load_containers',[clientController::class,'load_sky_containers']);
            
        });
    });
        
    });

    Route::post('/get_currencies', [dataController::class,'get_currencies']);

});

Route::middleware(['chkAuthAdmin'])->group(function(){

    Route::post('/del_recs', [dataController::class,'del_recs']);
    Route::post('/del_recs_permanent', [dataController::class,'del_recs_permanent']);
    Route::post('/restore_recs', [dataController::class,'restore_recs']);
    Route::post('/get_currencies', [dataController::class,'get_currencies']);

    // Admin-only settings mutations. The full /settings prefix block
    // further down also wears type:admin; these two stragglers are
    // outside that block and have to be guarded here. See gap #3.
    Route::middleware(['type:admin'])->group(function () {
        Route::post('/settings/save_general',[settingsController::class,'save_general']);

        Route::get('/settings', function () {
            return view('pages.settings.index',[
                'page' => 'settings'
            ]);
        });
    });


    Route::prefix('clients')->group(function(){

        Route::get('/all', function () {
            return view('pages.clients.index',[
                'section' => 'clients',
                'page'    => 'clients'
            ]);
        });

        Route::post('/load',[clientsController::class,'load']);
        Route::post('/edit',[clientsController::class,'edit']);
        Route::post('/create',[clientsController::class,'create']);
        Route::post('/save',[clientsController::class,'save']);
        Route::post('/deposit',[clientsController::class,'deposit']);
        Route::post('/withdraw',[clientsController::class,'withdraw']);
        Route::post('/withdraw_commission',[clientsController::class,'withdraw_commission']);
        Route::post('/del_transaction',[clientsController::class,'del_transaction']);
        Route::post('/transfer_client',[clientsController::class,'transfer_clients']);
        Route::post('/transfer',[clientsController::class,'transfer']);
        Route::post('/get_client_data',[clientsController::class,'get_client_data']);
        Route::post('/get_code',[clientsController::class,'get_code']);


        Route::prefix('reports')->group(function(){
            Route::post('/all',[clientsReportsController::class,'all']);
            Route::post('/pending',[clientsReportsController::class,'pending']);
            Route::post('/approveReject',[clientsReportsController::class,'approveReject']);
            Route::post('/deposit',[clientsReportsController::class,'deposit']);
            Route::post('/withdraw',[clientsReportsController::class,'withdraw']);
            Route::post('/withdraw_commission',[clientsReportsController::class,'withdraw_commission']);
            Route::post('/transfer',[clientsReportsController::class,'transfer']);
            Route::post('/transfer_clients',[clientsReportsController::class,'transfer_clients']);
            Route::post('/exp_report',[clientsReportsController::class,'exp']);
            Route::get('/deposit_print/{client_id}',[clientsReportsController::class,'deposit_print']);
            Route::get('/statement/{client_id}',    [clientsReportsController::class,'statement']);
        });
    });


    Route::prefix('profits')->group(function(){

        Route::get('/', function () {
            return view('pages.profits.index',[
                'section' => 'profits',
                'page'    => 'profits'
            ]);
        });

        Route::post('/load',[profitsController::class,'load']);
        Route::get('/container/{type}/{id}', [profitsController::class, 'container'])
            ->where(['type' => 'sky|sea', 'id' => '[0-9]+']);
    });



    Route::prefix('matching')->group(function(){

        Route::get('/', function () {
            return view('pages.matching.index',[
                'section' => 'matching',
                'page'    => 'matching'
            ]);
        });

        Route::post('/load',[matchingController::class,'load']);
    });

    Route::prefix('old_balance_archive')->group(function(){

        Route::get('/', function () {
            return view('pages.old_balance_archive.index',[
                'section' => 'old_balance_archive',
                'page'    => 'old_balance_archive'
            ]);
        });

        Route::post('/load',[oldBalanceArchiveController::class,'load']);
    });


    Route::prefix('analytics')->group(function(){
        Route::post('/load',[analyticsController::class,'load']);
    });
    
    Route::prefix('shipping')->group(function(){

        Route::prefix('sea')->group(function(){

            Route::get('/', function () {
                return view('pages.shipping.sea.index',[
                    'section' => 'sea',
                    'page'    => 'sea'
                ]);
            });

            Route::get('/container/{id}', function ($id) {
                return view('pages.shipping.sea.containers.container_data',[
                    'section' => 'sea',
                    'page'    => 'sea',
                    'id'      => $id
                ]);
            });

            Route::post('/load_canceled',[seaController::class,'load_canceled']);
            Route::post('/cancel_in_container',[seaController::class,'cancel_in_container']);
            Route::post('/print_container',[seaController::class,'print_container']);
            Route::post('/add_link',[seaController::class,'add_link']);
            Route::post('/load_canceled_containers',[seaController::class,'load_canceled_containers']);
            Route::post('/cancel_container',[seaController::class,'cancel_container']);
            Route::post('/change_status_custom_container',[seaController::class,'change_status_custom_container']);
            Route::post('/insert_exist',[seaController::class,'insert_exist']);
            Route::post('/load_containers',[seaController::class,'load_containers']);
            Route::post('/save_container',[seaController::class,'save_container']);
            Route::post('/change_status',[seaController::class,'change_status']);
            Route::post('/new_custom_container',[seaController::class,'new_custom_container']);
            Route::post('/show_custom_container',[seaController::class,'show_custom_container']);
            Route::post('/withdraw_custom_broker',[seaController::class,'withdraw_custom_broker']);
            Route::post('/print_packing_list',[seaController::class,'print_packing_list']);
            Route::post('/print_delivery',[seaController::class,'print_delivery']);

            Route::post('/load_outside',[seaController::class,'load_outside']);
            Route::post('/create_container',[seaController::class,'create_container']);

            Route::post('/load_received',[seaController::class,'load_received']);
            Route::post('/new_received',[seaController::class,'new_received']);
            Route::post('/save_received',[seaController::class,'save_received']);
            Route::post('/edit_received',[seaController::class,'edit_received']);
            Route::post('/cancel',[seaController::class,'cancel']);
            
            Route::post('/load_inside',[seaController::class,'load_inside']);
            Route::post('/get_eject_modal',[seaController::class,'get_eject_modal']);
            Route::post('/eject',[seaController::class,'eject']);
            
        });



        Route::prefix('sky')->group(function(){

            Route::get('/', function () {
                return view('pages.shipping.sky.index',[
                    'section' => 'sky',
                    'page'    => 'sky'
                ]);
            });

            Route::get('/container/{id}', function ($id) {
                return view('pages.shipping.sky.containers.container_data',[
                    'section' => 'sky',
                    'page'    => 'sky',
                    'id'      => $id
                ]);
            });

            
            Route::post('/load_canceled',[skyController::class,'load_canceled']);

            Route::post('/print_container',[skyController::class,'print_container']);
            Route::post('/cancel_in_container',[skyController::class,'cancel_in_container']);
            Route::post('/add_link',[skyController::class,'add_link']);
            Route::post('/load_containers',[skyController::class,'load_containers']);
            Route::post('/insert_exist',[skyController::class,'insert_exist']);
            Route::post('/load_canceled_containers',[skyController::class,'load_canceled_containers']);
            Route::post('/cancel_container',[skyController::class,'cancel_container']);
            Route::post('/save_container',[skyController::class,'save_container']);
            Route::post('/change_status',[skyController::class,'change_status']);
            Route::post('/new_custom_container',[skyController::class,'new_custom_container']);
            Route::post('/show_custom_container',[skyController::class,'show_custom_container']);
            Route::post('/withdraw_custom_broker',[skyController::class,'withdraw_custom_broker']);
            Route::post('/print_packing_list',[skyController::class,'print_packing_list']);
            Route::post('/print_delivery',[skyController::class,'print_delivery']);

            Route::post('/load_outside',[skyController::class,'load_outside']);
            Route::post('/create_container',[skyController::class,'create_container']);

            Route::post('/load_received',[skyController::class,'load_received']);
            Route::post('/new_received',[skyController::class,'new_received']);
            Route::post('/save_received',[skyController::class,'save_received']);
            Route::post('/edit_received',[skyController::class,'edit_received']);
            Route::post('/cancel',[skyController::class,'cancel']);
            
            Route::post('/load_inside',[skyController::class,'load_inside']);
            Route::post('/get_eject_modal',[skyController::class,'get_eject_modal']);
            Route::post('/eject',[skyController::class,'eject']);

        });

        // Per-piece tracking sticker PDF (one per page, 100×150mm). Works
        // for both store_sea and store_sky. Auto-generates pieces on first
        // request if they're missing (legacy rows).
        Route::get('/stickers/{source_table}/{source_id}',
            [shipmentStickersController::class, 'stickerPdf'])
            ->where('source_table', 'store_(sea|sky)')
            ->where('source_id', '[0-9]+');

        // Bulk sticker PDF for every shipment in a container/trip.
        // PIN-protected — POST so the secret never lands in URLs/logs.
        Route::post('/stickers/container/{container_table}/{container_id}',
            [shipmentStickersController::class, 'bulkContainerStickers'])
            ->where('container_table', 'containers_(sea|sky)')
            ->where('container_id', '[0-9]+');
    });

    Route::prefix('branches')->group(function(){

        Route::get('/all', function () {
            return view('pages.branches.index',[
                'section' => 'branches',
                'page'    => 'branches'
            ]);
        });

        Route::post('/load',[branchesController::class,'load']);
        Route::post('/edit',[branchesController::class,'edit']);
        Route::post('/create',[branchesController::class,'create']);
        Route::post('/save',[branchesController::class,'save']);
    });

    Route::prefix('company')->group(function(){

        Route::get('/accounting', function () {
            return view('pages.company_accounting.index',[
                'section' => 'company_accounting',
                'page'    => 'company_accounting'
            ]);
        });

        Route::post('/load',[companyAccountController::class,'load']);

        
        Route::post('/deposit_branch',[branchesController::class,'deposit_branch']);
        Route::post('/deposit_commission',[branchesController::class,'deposit_commission']);
        Route::post('/add_expenses',[branchesController::class,'add_expenses']);
        Route::post('/transfer_branch',[branchesController::class,'transfer_branch']);
        Route::post('/fix_branch',[branchesController::class,'fix_branch']);
        
        Route::post('/sea_withdraw',[seaController::class,'sea_withdraw']);
        Route::post('/sky_withdraw',[skyController::class,'sky_withdraw']);
    });


    Route::prefix('treasury')->group(function(){

        Route::get('/', function () {
            return view('pages.treasury.index',[
                'section' => 'treasury',
                'page'    => 'treasury'
            ]);
        });


        Route::post('/load',[treasuryController::class,'load']);
        Route::post('/get_totals',[treasuryController::class,'get_totals']);
        Route::post('/load_balance',[treasuryController::class,'load_balance']);
    });

    Route::prefix('suppliers')->group(function(){

        Route::get('/', function () {
            return view('pages.suppliers.index',[
                'section' => 'suppliers',
                'page'    => 'suppliers'
            ]);
        });


        Route::post('/load',[suppliersController::class,'load']);
        Route::post('/edit',[suppliersController::class,'edit']);
        Route::post('/create',[suppliersController::class,'create']);
        Route::post('/save',[suppliersController::class,'save']);
        Route::post('/deposit',[suppliersController::class,'deposit']);
        Route::post('/reports',[suppliersController::class,'reports']);

    });

    Route::prefix('customs_brokers')->group(function(){

        Route::get('/', function () {
            return view('pages.customs_brokers.index',[
                'section' => 'customs_brokers',
                'page'    => 'customs_brokers'
            ]);
        });

        Route::post('/load',[customsBrokersController::class,'load']);
        Route::post('/edit',[customsBrokersController::class,'edit']);
        Route::post('/create',[customsBrokersController::class,'create']);
        Route::post('/save',[customsBrokersController::class,'save']);
        Route::post('/deposit',[customsBrokersController::class,'deposit']);
        Route::post('/reports',[customsBrokersController::class,'reports']);

    });

    Route::prefix('sourcing')->group(function () {
        Route::get('/',                  [sourcingController::class, 'index']);
        Route::get('/dashboard',         [sourcingController::class, 'dashboard']);
        Route::get('/board',             [sourcingController::class, 'board']);
        Route::get('/commissions',       [sourcingController::class, 'commissionsReport']);
        Route::get('/payments',          [sourcingController::class, 'openBalancesReport']);
        Route::post('/load',             [sourcingController::class, 'load']);
        Route::post('/create',           [sourcingController::class, 'create']);
        Route::post('/update',           [sourcingController::class, 'update']);
        Route::post('/cancel',           [sourcingController::class, 'cancel']);
        Route::post('/fulfill',          [sourcingController::class, 'markFulfilled']);
        Route::post('/quotes/add',       [sourcingController::class, 'addQuote']);
        Route::post('/quotes/accept',    [sourcingController::class, 'acceptQuote']);

        // Proforma: line items + photos + payment plan + settings.
        Route::post('/items/add',                  [sourcingController::class, 'addItem']);
        Route::post('/items/update',               [sourcingController::class, 'updateItem']);
        Route::post('/items/delete',               [sourcingController::class, 'deleteItem']);
        Route::post('/items/photos/upload',        [sourcingController::class, 'uploadItemPhotos']);
        Route::post('/items/photos/delete',        [sourcingController::class, 'deleteItemPhoto']);
        Route::post('/items/photos/set-primary',   [sourcingController::class, 'setPrimaryPhoto']);
        Route::post('/proforma/settings',          [sourcingController::class, 'updateProformaSettings']);
        Route::post('/proforma/payment-plan',      [sourcingController::class, 'generatePaymentPlan']);
        Route::post('/proforma/payments/add',      [sourcingController::class, 'addPayment']);
        Route::post('/proforma/payments/update',   [sourcingController::class, 'updatePayment']);
        Route::post('/proforma/payments/delete',   [sourcingController::class, 'deletePayment']);
        Route::post('/proforma/payments/mark-paid',[sourcingController::class, 'markInstallmentPaid']);

        // Phase 2: PDF, send-link, on-behalf approval.
        Route::get('/{id}/pdf',                    [sourcingController::class, 'proformaPdf'])->whereNumber('id');
        Route::post('/{id}/send',                  [sourcingController::class, 'sendProforma'])->whereNumber('id');
        Route::post('/{id}/approve-on-behalf',     [sourcingController::class, 'approveOnBehalf'])->whereNumber('id');

        // Phase 3: handoff to freight container.
        Route::get('/{id}/handoff/{kind}',         [sourcingController::class, 'handoffForm'])
            ->where(['id' => '[0-9]+', 'kind' => 'sky|sea']);
        Route::post('/{id}/handoff',               [sourcingController::class, 'handoffSubmit'])->whereNumber('id');

        // Phase 4: profit dashboard, email PDF, activity timeline.
        Route::get('/{id}/profit',                 [sourcingController::class, 'profitDashboard'])->whereNumber('id');
        Route::post('/{id}/email',                 [sourcingController::class, 'emailToClient'])->whereNumber('id');

        // Phase 5: clone + reminder.
        Route::post('/{id}/clone',                 [sourcingController::class, 'cloneProforma'])->whereNumber('id');
        Route::post('/{id}/reminder',              [sourcingController::class, 'sendReminder'])->whereNumber('id');

        // Item status (per-item delivery tracking).
        Route::post('/items/status',               [sourcingController::class, 'updateItemStatus']);

        // Phase 6: documents + bulk export.
        Route::post('/documents/upload',           [sourcingController::class, 'uploadDocuments']);
        Route::post('/documents/delete',           [sourcingController::class, 'deleteDocument']);
        Route::post('/documents/visibility',       [sourcingController::class, 'setDocumentVisibility']);
        Route::post('/bulk-pdf',                   [sourcingController::class, 'bulkPdf']);

        // Phase 7: change requests + container sync.
        Route::post('/change-requests/respond',    [sourcingController::class, 'respondChangeRequest']);
        Route::post('/{id}/sync-from-container',   [sourcingController::class, 'syncFromContainer'])->whereNumber('id');

        // Phase 8: rotate share token.
        Route::post('/{id}/rotate-token',          [sourcingController::class, 'rotateToken'])->whereNumber('id');

        // Phase 9: soft-delete / restore / hard-delete.
        Route::post('/{id}/trash',                 [sourcingController::class, 'trash'])->whereNumber('id');
        Route::post('/{id}/restore',               [sourcingController::class, 'restore'])->whereNumber('id');
        Route::post('/{id}/destroy',               [sourcingController::class, 'destroy'])->whereNumber('id');

        // Phase 9: client portal token mint/rotate.
        Route::post('/clients/{clientId}/portal-token', [sourcingController::class, 'mintClientPortalToken'])->whereNumber('clientId');

        // Phase 10: board status quick-move + bulk actions + markup.
        Route::post('/{id}/status',                [sourcingController::class, 'quickMoveStatus'])->whereNumber('id');
        Route::post('/bulk/trash',                 [sourcingController::class, 'bulkTrash']);
        Route::post('/bulk/restore',               [sourcingController::class, 'bulkRestore']);
        Route::post('/{id}/apply-markup',          [sourcingController::class, 'applyMarkup'])->whereNumber('id');

        // Phase 11: catalog + funnel + reminder run.
        Route::get('/catalog',                     [sourcingController::class, 'catalogIndex']);
        Route::get('/catalog/manage',              [sourcingController::class, 'catalogManage']);
        Route::post('/catalog/save',               [sourcingController::class, 'catalogSave']);
        Route::post('/catalog/delete',             [sourcingController::class, 'catalogDelete']);
        Route::get('/funnel',                      [sourcingController::class, 'funnel']);
        Route::post('/reminders/run',              [sourcingController::class, 'runReminders']);

        // Phase 12: PO linkage + delivery commitments.
        Route::get('/po-search',                   [sourcingController::class, 'poSearch']);
        Route::post('/items/dates',                [sourcingController::class, 'updateItemDates']);
        Route::post('/{id}/po-link',               [sourcingController::class, 'poLink'])->whereNumber('id');
        Route::post('/{id}/po-unlink',             [sourcingController::class, 'poUnlink'])->whereNumber('id');

        // Phase 13: versioning + diff.
        Route::post('/{id}/snapshot',              [sourcingController::class, 'manualSnapshot'])->whereNumber('id');
        Route::get('/{id}/versions/{versionNo}',   [sourcingController::class, 'versionPdf'])
            ->where(['id' => '[0-9]+', 'versionNo' => '[0-9]+']);
        Route::get('/{id}/diff',                   [sourcingController::class, 'diff'])->whereNumber('id');

        // Phase 14: supplier reliability insights.
        Route::get('/insights/suppliers',          [sourcingController::class, 'supplierInsights']);

        // Phase 15: per-proforma health trend (JSON).
        Route::get('/{id}/health-trend',           [sourcingController::class, 'healthTrend'])->whereNumber('id');

        // /sourcing/{id} must come last — anything more specific above wins.
        Route::get('/{id}',              [sourcingController::class, 'show'])->whereNumber('id');
    });

    // Admin-only: managing other users + changing their passwords.
    // The inline check in usersController also enforces this; the
    // middleware is the outer defense-in-depth layer. See gap #3.
    Route::middleware(['type:admin'])->prefix('users')->group(function(){

        Route::get('/', function () {
            return view('pages.users.index',[
                'section' => 'users',
                'page'    => 'users'
            ]);
        });


        Route::post('/load',[usersController::class,'load']);
        Route::post('/delete',[usersController::class,'delete']);
        Route::post('/create',[usersController::class,'create']);
        Route::post('/save',[usersController::class,'save']);
        Route::post('/get',[usersController::class,'get']);
        Route::post('/change_pass',[usersController::class,'change_pass']);
    });

    // Admin-only: system-wide settings (company info, FX defaults, etc).
    Route::middleware(['type:admin'])->prefix('settings')->group(function(){

        Route::get('/', function () {
            return view('pages.settings.index',[
                'section' => 'settings',
                'page'    => 'settings'
            ]);
        });

        Route::post('/save',[settingsController::class,'save']);
        Route::post('/save2',[settingsController::class,'save2']);
        Route::post('/update_exchange',[settingsController::class,'update_exchange']);
    });

    Route::prefix('audit')->group(function(){
        Route::get('/', function () {
            return view('pages.audit.index',[
                'section' => 'audit',
                'page'    => 'audit'
            ]);
        });
        Route::post('/load',[auditController::class,'load']);
    });

    Route::prefix('reconciliation')->group(function(){
        Route::get('/', [reconciliationController::class, 'index']);
        Route::post('/clients',  [reconciliationController::class, 'clients']);
        Route::post('/branches', [reconciliationController::class, 'branches']);
    });

    Route::prefix('receipts')->group(function(){
        Route::get('/for/{source_table}/{source_id}', [receiptsController::class, 'forTransaction'])
            ->where('source_table', '[a-z_]+')
            ->where('source_id', '[0-9]+');
        Route::get('/{id}',                           [receiptsController::class, 'show'])
            ->where('id', '[0-9]+');
        Route::post('/{id}/void',                     [receiptsController::class, 'void'])
            ->where('id', '[0-9]+');
    });

    Route::prefix('accounting')->group(function(){
        Route::get('/chart',          [accountingController::class, 'chartIndex']);
        Route::get('/trial-balance',  [accountingController::class, 'trialBalance']);
        Route::get('/profit-loss',    [accountingController::class, 'pnlStatement']);
        Route::get('/balance-sheet',  [accountingController::class, 'balanceSheet']);
        Route::get('/cash-flow',      [accountingController::class, 'cashFlowStatement']);
        Route::get('/journal',        [accountingController::class, 'dailyJournal']);
        Route::get('/ar-aging',       [accountingController::class, 'arAging']);
        Route::get('/supplier-aging', [accountingController::class, 'supplierAging']);
        Route::get('/broker-aging',   [accountingController::class, 'brokerAging']);
        Route::get('/fx-history',     [accountingController::class, 'fxHistory']);

        Route::get('/periods',                    [accountingController::class, 'periodsIndex']);
        Route::post('/periods/{id}/close',        [accountingController::class, 'periodClose'])->where('id', '[0-9]+');
        Route::post('/periods/{id}/reopen',       [accountingController::class, 'periodReopen'])->where('id', '[0-9]+');

        Route::get('/cash-counts',                [accountingController::class, 'cashCountIndex']);
        Route::post('/cash-counts',               [accountingController::class, 'cashCountStore']);
        Route::post('/cash-counts/{id}/adjust',   [accountingController::class, 'cashCountAdjust'])->where('id', '[0-9]+');
        Route::get('/treasury-by-branch',         [accountingController::class, 'treasuryByBranch']);

        Route::get('/prepayments',                [accountingController::class, 'prepaymentsIndex']);
        Route::post('/prepayments/register',      [accountingController::class, 'prepaymentRegister']);
        Route::post('/prepayments/{id}/apply',    [accountingController::class, 'prepaymentApply'])->where('id', '[0-9]+');

        Route::get('/owners',                     [accountingController::class, 'ownersIndex']);
        Route::post('/owners',                    [accountingController::class, 'ownersStore']);
        Route::post('/owners/{id}',               [accountingController::class, 'ownersUpdate'])->where('id', '[0-9]+');
        Route::delete('/owners/{id}',             [accountingController::class, 'ownersDelete'])->where('id', '[0-9]+');
        Route::get('/owners-ledger',              [accountingController::class, 'ownersLedger']);

        Route::get('/journal-entries',            [journalController::class, 'entriesIndex']);
        Route::get('/journal-entries/{id}',       [journalController::class, 'entryShow'])->where('id', '[0-9]+');
        Route::post('/journal-entries/{id}/reverse', [journalController::class, 'entryReverse'])->where('id', '[0-9]+');
        Route::get('/journal-trial-balance',      [journalController::class, 'trialBalanceView']);
        Route::get('/drift',                      [accountingController::class, 'driftReport']);
    });

    Route::get('/', function () {
        return view('pages.home.index',[
            'section' => 'home',
            'page'    => 'home'
        ]);
    });

    
});

// Public shipment tracking (no auth — that's the whole point of a QR).
// View-model in the controller is field-allow-listed; nothing sensitive
// leaks here. Rate-limited per IP to make code-space enumeration painful
// without breaking the "scan a sticker once" flow.
Route::get('/track/{code}', [shipmentStickersController::class, 'publicTrack'])
    ->where('code', '[A-Za-z0-9\-]+')
    ->middleware('throttle:30,1');

Route::get('/get_lang',[langController::class,'get_lang']);

Route::get('/login/{selected_lang?}', function ($selected_lang = 'en') {
    return view('login',[
        'page' => 'login',
        'section' => 'login',
        'selected_lang' => $selected_lang
    ]);
});

Route::prefix('auth')->group(function(){

    Route::prefix('user')->group(function(){
        // Per-identifier rate limit (see AppServiceProvider::boot). The legacy
        // `throttle:5,1` was per-IP only, which credential stuffing bypassed.
        Route::post('/login', [usersController::class,'login'])->middleware('throttle:login');
    });

});


Route::get('/change_lang/{lang}',[usersController::class,'change_lang']);

/* -----------------------------------------------------------------
 *  Public proforma share — no login required. The share_token is the
 *  capability: anyone with the link can view and approve. Tokens are
 *  90 days; the controller returns 410 Gone after expiry.
 * ----------------------------------------------------------------- */
Route::get('/proforma/{token}',                  [sourcingController::class, 'publicProforma']);
Route::get('/proforma/{token}/pdf',              [sourcingController::class, 'publicProformaPdf']);
Route::post('/proforma/{token}/approve',         [sourcingController::class, 'publicApprove']);
Route::post('/proforma/{token}/request-changes', [sourcingController::class, 'publicRequestChanges']);

Route::get('/portal/{token}',                    [sourcingController::class, 'publicClientPortal']);
