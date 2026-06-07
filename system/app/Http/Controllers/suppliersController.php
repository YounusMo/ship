<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;
use App\Http\Controllers\branchesController;
use App\Http\Controllers\treasuryController;

class suppliersController extends Controller
{
    public function load(Request $request){
        try {
            
            $get = DB::table('suppliers');
            $get = $get->orderBy('id','DESC');
            
            if($request->search){
                
                $columns = Schema::getColumnListing('suppliers');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $show_deleted = $request->showDeleted;
            $get = $get->where('deleted',$show_deleted);

            $get = $get->where('not_active','false');
            
            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            return view('pages.suppliers.table',compact('get','count','show_deleted'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }


    public function create(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {

                $dataController = new dataController();
                $curs = $dataController->currencies;
                
                $name    = trim($request->name);
                $sky_sea = trim($request->sky_sea);

                $date = date('Y-m-d'); 
                $time = date('H:i:s'); 
                $by   = auth()->user()->id; 

                if($name){
                    $data = [
                        'sky_sea'      => $sky_sea,
                        'name'         => $name,
                        'created_date' => $date,
                        'created_time' => $time,
                        'created_by'   => $by,
                        'deleted'      => 'false',
                        'not_active'   => 'false',
                    ];

                    foreach ($curs as $key => $value) {
                        $data['balance_'.$value['code']] = 0;
                    }
                    
                    DB::table('suppliers')->insert($data);

                    Cache::forget('suppliers');
                    Cache::forget('suppliers_');
                    
                    $response = response()->json(['type' => 'success'],200);
                }else{
                    $response = response()->json(['type' => 'error'],500);
                }
            });

            return $response;
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function save(Request $request){
        try {
            
            $response = null;
            
            DB::transaction(function () use ($request, &$response) {

                $name = trim($request->name);
                $sky_sea = trim($request->sky_sea);

                if($name ){
                    $data = [
                        'name'  => $name,
                        'sky_sea'=> $sky_sea,
                    ];

                    DB::table('suppliers')->where('id',$request->id)->update($data);

                    Cache::forget('suppliers');
                    Cache::forget('suppliers_');

                    $response = response()->json(['type' => 'success'],200);
                }else{
                    $response = response()->json(['type' => 'error'],500);
                }
            });

            return $response;
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function edit(Request $request){
        $get = DB::table('suppliers')->where('id',$request->id)->first();

        if($get){
            return view('pages.suppliers.edit',compact('get'));
        }else{
            return response()->json(['type' => 'error'],500);
        }
    }

    public function withdraw(Request $request){
        $this->assertSupplierExists($request->supplier_id);
        $this->assertPeriodOpen(date('Y-m-d'));
        try {

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $dataController   = new dataController();
                
                $container_id       = $request->container_id;
                $container_number   = $request->container_number;
                $transaction_number = $request->transaction_number;
                $supplier_id        = $request->supplier_id;
                $plus_minus         = $request->plus_minus;
                $value              = $request->value;
                $type               = $request->type;
                $currency           = $request->currency;
                $sky_sea            = $request->sky_sea;
 
                $created_date = date('Y-m-d');
                $created_time = date('H:i:s');
                $created_by   = auth()->user()->id;

                $last_auto_id = $dataController->supplier_code;
                
                $last_auto_id_ = DB::table('suppliers_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;
                
                DB::table('suppliers_transactions')->insert([
                    'container_id'       => $container_id,
                    'container_number'   => $container_number,
                    'transaction_number' => $transaction_number,
                    'supplier_id'        => $supplier_id,
                    'value'              => $value,
                    'auto_id'            => $auto_id,
                    'currency'           => $currency,
                    'plus_minus'         => $plus_minus,
                    'sky_sea'            => $sky_sea,
                    'type'               => $type,
                    'created_date'       => $created_date,
                    'created_time'       => $created_time,
                    'created_by'         => $created_by,
                ]);

                $this->update_balance($supplier_id);

                $err  = false;

                if($err){
                    $response = response()->json(['type' => 'error'],500);
                }else{
                   $response = response()->json(['type' => 'success'],200);
                }
            });

            return $response;

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function deposit(Request $request){
        $this->assertSupplierExists($request->supplier_id);
        $this->assertPeriodOpen(date('Y-m-d'));
        try {

            $dataController   = new dataController();
            $treasuryController   = new treasuryController();
            $branchesController   = new branchesController();
            $currency_exchange_rates = $dataController->currency_exchange_rates;

            $transaction_number = $request->transaction_number;
            $supplier_id        = $request->supplier_id;
            $value              = $request->value;
            $branch             = $request->branch;
            $notes              = $request->notes;
            $from_value         = $request->value;
            $type               = $request->type ?? 'deposit';
            $container_id       = $request->container_id ?? null;
            $container_number   = $request->container_number ?? null;
            $sky_sea            = $request->sky_sea ?? null;
            $currency           = $request->currency;

            // Read-only balance check happens BEFORE the transaction so we can
            // short-circuit with a clean response (returning from inside a
            // DB::transaction closure wouldn't bubble to the caller).
            $calc = $branchesController->allow_complete_blance($branch,$value,$currency);
            if(! $calc){
                return response()->json(['type' => 'balance_err'],200);
            }

            $rate = 0;
            if($currency !== 'usd'){
                $rate = floatval($currency_exchange_rates[$currency]);
            }

            $created_date = date('Y-m-d');
            $created_time = date('H:i:s');
            $created_by   = auth()->user()->id;

            $purpose = $this->normalizePurpose($request->purpose, $dataController->supplier_deposit_purposes);

            $response = null;
            DB::transaction(function () use (
                $request, $treasuryController, $branchesController, $dataController,
                $transaction_number, $supplier_id, $value, $branch, $notes,
                $from_value, $type, $container_id, $container_number, $sky_sea,
                $currency, $rate, $created_date, $created_time, $created_by, $purpose,
                &$response
            ) {
                // auto_id allocation must live inside the transaction so the
                // SELECT/INSERT pair sees a consistent view.
                $last_auto_id  = $dataController->supplier_code;
                $last_auto_id_ = DB::table('suppliers_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id + 1 : $last_auto_id;

                DB::table('suppliers_transactions')->insert([
                    'transaction_number' => $transaction_number,
                    'supplier_id'        => $supplier_id,
                    'container_number'   => $container_number,
                    'container_id'       => $container_id,
                    'sky_sea'            => $sky_sea,
                    'branch'             => $branch,
                    'exchange_rate'      => $rate,
                    'value'              => $value,
                    'auto_id'            => $auto_id,
                    'currency'           => $currency,
                    'from_value'         => $from_value,
                    'from_currency'      => $currency,
                    'notes'              => $notes,
                    'purpose'            => $purpose,
                    'plus_minus'         => 'plus',
                    'type'               => $type,
                    'created_date'       => $created_date,
                    'created_time'       => $created_time,
                    'created_by'         => $created_by,
                ]);

                $this->update_balance($supplier_id);

                $treasuryData = json_encode(['supplier_id' => $supplier_id]);

                if($branch){
                    $branchesController->update_balance($branch,$currency);
                    $treasuryController->insert($transaction_number,'supplier_deposit','minus',$auto_id,$treasuryData,$from_value,$currency,0,$branch,$notes,null);
                }

                $this->logAudit(
                    'supplier_deposit',
                    'suppliers_transactions',
                    $auto_id,
                    [
                        'supplier_id'        => $supplier_id,
                        'branch'             => $branch,
                        'value'              => $value,
                        'currency'           => $currency,
                        'purpose'            => $purpose,
                        'transaction_number' => $transaction_number,
                    ],
                    'Supplier deposit'
                );

                // Double-entry journal: supplier deposit (we pay them).
                //   Dr 1200 Prepaid to suppliers (asset ↑)
                //   Cr 1000 Cash on hand         (asset ↓)
                // No try/catch: a failure here rolls back the supplier insert,
                // balance updates, and treasury row above, keeping the ledger
                // and the journal in lockstep.
                //
                // Cost-object: when the operator pinned this deposit to a
                // specific container (sky/sea), tag both lines so per-flight
                // / per-container profit slicing in the ledger picks up this
                // expense. Unpinned deposits stay NULL — they're operating
                // float that isn't attributable to a single deliverable.
                $costObjectType = null;
                $costObjectId   = null;
                if (!empty($container_id) && (int) $container_id > 0 && in_array($sky_sea, ['sky', 'sea'], true)) {
                    $costObjectType = 'container_' . $sky_sea;
                    $costObjectId   = (int) $container_id;
                }
                (new \App\Http\Controllers\journalController())->record([
                    'entry_date'         => date('Y-m-d'),
                    'kind'               => 'supplier_deposit',
                    'description'        => 'Paid supplier ' . $value . ' ' . strtoupper($currency),
                    'source_table'       => 'suppliers_transactions',
                    'source_id'          => $auto_id,
                    'transaction_number' => $transaction_number,
                    'branch_id'          => is_numeric($branch) ? (int) $branch : null,
                    'cost_object_type'   => $costObjectType,
                    'cost_object_id'     => $costObjectId,
                    'lines'              => [
                        ['account_code' => '1200', 'dr' => (float) $value, 'cr' => 0, 'currency' => $currency,
                         'counterparty_type' => 'supplier', 'counterparty_id' => (int) $supplier_id],
                        ['account_code' => '1000', 'dr' => 0, 'cr' => (float) $value, 'currency' => $currency,
                         'counterparty_type' => 'supplier', 'counterparty_id' => (int) $supplier_id],
                    ],
                ]);

                $response = response()->json(['type' => 'success', $supplier_id], 200);
            });

            return $response;

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function update_balance($supplier_id){
        $dataController = new dataController();
        $currencies     = $dataController->currencies;

        foreach ($currencies as $key => $cur) {
            $plus  = 0;
            $minus = 0;

            $get = DB::table('suppliers_transactions')->where('currency',$cur['code'])->where('supplier_id',$supplier_id)->get();

            foreach ($get as $key => $value) {
                if($value->plus_minus === 'plus'){
                    $plus += floatval($value->value);
                }else{
                    $minus += floatval($value->value);
                }
            }


            DB::table('suppliers')->where('id',$supplier_id)->update([
                'balance_'.$cur['code'] => $plus - $minus
            ]);
        }

    }


    public function reports(Request $request){
        $get = DB::table('suppliers_transactions');
        
        // $get = $get->where('type','deposit');
        $get = $get->where('supplier_id',$request->supplier_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        return view('pages.reports.suppliers.reports_table',compact('get','users'));
    }
}
