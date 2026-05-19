<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;


class branchesController extends Controller
{
    public function load(Request $request){

        try {

            if (!in_array(auth()->user()->type , ['admin'])) {
                abort(403, 'Unauthorized');
            }
            
            $get = DB::table('branches');
            $get = $get->orderBy('id','DESC');
            
            if($request->search){
                
                $columns = Schema::getColumnListing('branches');
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
            
            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));
            
            return view('pages.branches.table',compact('get','count','show_deleted'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function create(Request $request){
        try {

            if (!in_array(auth()->user()->type , ['admin'])) {
                abort(403, 'Unauthorized');
            }

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {

                $dataController = new dataController();
                $curs = $dataController->currencies;

                $name        = trim($request->name);
                $name_en     = trim($request->name_en);
                $name_zh     = trim($request->name_zh);

                $date = date('Y-m-d'); 
                $time = date('H:i:s'); 
                $by   = auth()->user()->id; 

                if($name){
                    $data = [
                        'name'         => $name,
                        'name_en'      => $name_en,
                        'name_zh'      => $name_zh,
                        'created_date' => $date,
                        'created_time' => $time,
                        'created_by'   => $by,
                        'deleted'      => 'false',
                    ];

                    foreach ($curs as $key => $value) {
                        $data['balance_'.$value['code']] = 0;
                    }
                    
                    DB::table('branches')->insert($data);

                    Cache::forget('branches');
                    Cache::forget('branches_compant_accounting');
                    Cache::forget('branches_clients');

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

            if (!in_array(auth()->user()->type , ['admin'])) {
                abort(403, 'Unauthorized');
            }
            $response = null;
            
            DB::transaction(function () use ($request, &$response) {

                $name        = trim($request->name);
                $name_en     = trim($request->name_en);
                $name_zh     = trim($request->name_zh);

                if($name){
                    $data = [
                        'name'         => $name,
                        'name_en'      => $name_en,
                        'name_zh'      => $name_zh,
                    ];

                    DB::table('branches')->where('id',$request->id)->update($data);
                    
                    Cache::forget('branches');
                    Cache::forget('branches_compant_accounting');

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
        $get = DB::table('branches')->where('id',$request->id)->first();

        if($get){
            return view('pages.branches.edit',compact('get'));
        }else{
            return response()->json(['type' => 'error'],500);
        }
    }


    public function update_balance_manual(Request $request){
        $get = DB::table('branches')->get();

        foreach ($get as $key => $value) {
            $this->update_balance($value->id)   ;
        }
    }

    public function update_balance($branch_id){
        $dataController = new dataController();
        $currencies     = $dataController->currencies;

        $usd = 0;
        $eur = 0;
        $den = 0;
        $cny = 0;

        foreach ($currencies as $key => $cur) {

            $get  = DB::table('clients_transactions')->where('status','approved')->whereNull('calc')->whereNotIn('type',['transfer','exp_custom_withdraw','exp_withdraw'])->where('branch',$branch_id)->where('currency',$cur['code'])->get();
            
            $get2 = DB::table('branches_transactions')->where('branch',$branch_id)->where(function($q) use($cur){
                $q->where('currency',$cur['code'])->orWhere('to_currency',$cur['code']);
            })->get();

            
            $plus  = 0;
            $minus = 0;

            $minus  += DB::table('suppliers_transactions')->where('branch',$branch_id)->where('plus_minus','plus')->where('from_currency',$cur['code'])->sum('from_value');
            $minus  += DB::table('customs_brokers_transactions')->where('branch',$branch_id)->where('plus_minus','plus')->where('from_currency',$cur['code'])->sum('value');

            foreach ($get as $key => $value) {
                if($value->plus_minus === 'plus'){
                    $plus += floatval($value->value) + floatval($value->commission);
                }

                if($value->plus_minus === 'minus'){
                    if($value->type === 'withdraw_commission'){
                        $plus += floatval($value->value);
                    }else{
                        $minus += floatval($value->value);
                    }
                }
            }

            foreach ($get2 as $key => $value) {
                if(!in_array($value->type,['transfer_branch'])){
                    if($value->plus_minus === 'plus'){
                        $plus += floatval($value->value);
                    }

                    if($value->plus_minus === 'minus'){
                        $minus += floatval($value->value);
                    }
                }

                if(in_array($value->type,['transfer_branch'])){
                    if($value->currency === $cur['code']){
                        $minus += floatval($value->value);
                    }

                    if($value->to_currency === $cur['code']){
                        $plus += floatval($value->transfer_value);
                    }
                }
            }

            if($cur['code'] === 'den'){
                $den = $plus - $minus;
            }
            
            if($cur['code'] === 'eur'){
                $eur = $plus - $minus;
            }
            
            if($cur['code'] === 'usd'){
                $usd = $plus - $minus;
            }
            
            if($cur['code'] === 'cny'){
                $cny = $plus - $minus;
            }

            // echo $cur['code'] .' : ' . $plus - $minus;
            // DB::table('branches')->where('id',$branch_id)->update([
            //     'balance_'.$cur['code'] => $plus - $minus
            // ]);

            DB::table('branches')->where('id',$branch_id)->update([
                'balance_usd' => $usd,
                'balance_eur' => $eur,
                'balance_cny' => $cny,
                'balance_den' => $den,
            ]);
        }
    }


    public function search_br_balance($branch_id , $from = null , $to = null){
        
        $dataController = new dataController();
        $currencies     = $dataController->currencies;

        $usd = 0;
        $eur = 0;
        $den = 0;
        $cny = 0;

        foreach ($currencies as $key => $cur) {

            $get  = DB::table('clients_transactions')->where('status','approved')->whereNull('calc')->whereNotIn('type',['transfer','exp_withdraw'])->whereNotNull('branch')->where('branch',$branch_id)->where('currency',$cur['code'])->get();
           
            $get2 = DB::table('branches_transactions')->where('branch',$branch_id)->where('currency',$cur['code'])->get();

            $plus  = 0;
            $minus = 0;

            // $minus  += DB::table('suppliers_transactions')->where('branch',$branch_id)->where('plus_minus','plus')->where('from_currency',$cur['code'])->sum('from_value');
            // $minus  += DB::table('customs_brokers_transactions')->where('branch',$branch_id)->where('plus_minus','plus')->where('from_currency',$cur['code'])->sum('value');

            foreach ($get as $key => $value) {
                if($value->plus_minus === 'plus'){
                    $plus += floatval($value->value) + floatval($value->commission);
                }

                if($value->plus_minus === 'minus'){
                    if($value->type === 'withdraw_commission'){
                        $plus += floatval($value->value);
                    }else{
                        $minus += floatval($value->value);
                    }
                }
            }

            // foreach ($get2 as $key => $value) {
            //     if(!in_array($value->type,['transfer_branch'])){
            //         if($value->plus_minus === 'plus'){
            //             $plus += floatval($value->value);
            //         }

            //         if($value->plus_minus === 'minus'){
            //             $minus += floatval($value->value);
            //         }
            //     }

            //     if(in_array($value->type,['transfer_branch'])){
            //         if($value->currency === $cur['code']){
            //             $minus += floatval($value->value);
            //         }

            //         if($value->to_currency === $cur['code']){
            //             $plus += floatval($value->transfer_value);
            //         }
            //     }
            // }

            if($cur['code'] === 'den'){
                $den = $plus - $minus;
            }
            
            if($cur['code'] === 'eur'){
                $eur = $plus - $minus;
            }
            
            if($cur['code'] === 'usd'){
                $usd = $plus - $minus;
            }
            
            if($cur['code'] === 'cny'){
                $cny = $plus - $minus;
            }
        }

        return [$usd,$eur,$cny,$den];
    }


    public function deposit_branch(Request $request){
        $this->assertPeriodOpen(date('Y-m-d'));
        try {

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_br_code;
                
                $last_auto_id_ = DB::table('branches_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();

                $transaction_number = $request->transaction_number;
                $value      = $request->value;
                $currency   = $request->currency;
                $branch     = $request->branch;
                $notes      = $request->notes;

                $err = false;

                if($transaction_number && $value && $currency && $branch){
                    $chk = DB::table('branches_transactions')->where('transaction_number',$transaction_number)->count();

                    if($chk == 0){
                        // $remaining_balance = $branchesController->update_balance($id,$currency,true);

                        $currency_exchange_rates = $dataController->currency_exchange_rates;

                        $exchange_rate = null;

                        $usd_value = $value;

                        if($currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = number_format((floatval($value) / $rate) , 2 , '.' , '');
                            $exchange_rate = $rate;
                        }

                        $purpose = $this->normalizePurpose($request->purpose, $dataController->branch_deposit_purposes);

                        DB::table('branches_transactions')->insert([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'currency'     => $currency,
                            'auto_id'      => $auto_id,
                            // 'remaining_balance' => $remaining_balance + $value,
                            'type'         => 'branch_deposit',
                            'plus_minus'   => 'plus',
                            'branch'       => $branch,
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'notes'        => $notes,
                            'purpose'      => $purpose,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        $branchesController->update_balance($branch);

                        $treasuryController->insert($transaction_number,'branch_deposit','plus',$auto_id,'',$value,$currency,0,$branch,$notes);

                        $this->logAudit(
                            'branch_deposit',
                            'branches_transactions',
                            $auto_id,
                            [
                                'branch'             => $branch,
                                'value'              => $value,
                                'currency'           => $currency,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Branch deposit'
                        );

                        // Double-entry journal: branch deposit (cash injection
                        // from owner is the typical case → equity contribution).
                        //   Dr 1000 Cash on hand     (asset ↑)
                        //   Cr 3000 Owner's equity   (equity ↑)
                        (new \App\Http\Controllers\journalController())->record([
                            'entry_date'         => date('Y-m-d'),
                            'kind'               => 'branch_deposit',
                            'description'        => 'Treasury deposit ' . $value . ' ' . strtoupper($currency) . ($purpose ? ' (' . $purpose . ')' : ''),
                            'source_table'       => 'branches_transactions',
                            'source_id'          => $auto_id,
                            'transaction_number' => $transaction_number,
                            'branch_id'          => (int) $branch,
                            'lines'              => [
                                ['account_code' => '1000', 'dr' => (float) $value, 'cr' => 0, 'currency' => $currency, 'branch_id' => (int) $branch],
                                ['account_code' => '3000', 'dr' => 0, 'cr' => (float) $value, 'currency' => $currency, 'branch_id' => (int) $branch],
                            ],
                        ]);

                        $err = false;
                    }else{
                        $err = true;
                    }

                }else{
                    $err = true;
                }

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


    public function deposit_commission(Request $request){
        $this->assertPeriodOpen(date('Y-m-d'));
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_br_code;
                
                $last_auto_id_ = DB::table('branches_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();

                $transaction_number = $request->transaction_number;
                $value      = $request->value;
                $currency   = $request->currency;
                $branch     = $request->branch;
                $notes      = $request->notes;

                $err = false;

                if($transaction_number && $value && $currency && $branch){
                    $chk = DB::table('branches_transactions')->where('transaction_number',$transaction_number)->count();

                    if($chk == 0){
                        // $remaining_balance = $branchesController->update_balance($id,$currency,true);

                        $currency_exchange_rates = $dataController->currency_exchange_rates;

                        $exchange_rate = null;

                        $usd_value = $value;

                        if($currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = number_format((floatval($value) / $rate) , 2 , '.' , '');
                            $exchange_rate = $rate;
                        }

                        $purpose = $this->normalizePurpose($request->purpose, $dataController->branch_commission_purposes);

                        DB::table('branches_transactions')->insert([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'currency'     => $currency,
                            'auto_id'      => $auto_id,
                            "data"         => json_encode(['type' => 'commission']),
                            // 'remaining_balance' => $remaining_balance + $value,
                            'type'         => 'branch_deposit',
                            'plus_minus'   => 'plus',
                            'branch'       => 15,
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'notes'        => $notes,
                            'purpose'      => $purpose,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        $branchesController->update_balance($branch);

                        $treasuryController->insert($transaction_number,'branch_deposit','plus',$auto_id,json_encode(['type' => 'commission']),$value,$currency,0,$branch,$notes);

                        $this->logAudit(
                            'branch_deposit_commission',
                            'branches_transactions',
                            $auto_id,
                            [
                                'branch'             => $branch,
                                'value'              => $value,
                                'currency'           => $currency,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Branch commission deposit'
                        );

                        $err = false;
                    }else{
                        $err = true;
                    }

                }else{
                    $err = true;
                }

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

    public function add_expenses(Request $request){
        $this->assertPeriodOpen(date('Y-m-d'));
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_br_code;
                
                $currency_exchange_rates = $dataController->currency_exchange_rates;

                $last_auto_id_ = DB::table('branches_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();

                $transaction_number = $request->transaction_number;
                $value      = $request->value;
                $currency   = $request->currency;
                $branch     = $request->branch;
                $notes      = $request->notes;
                $purpose    = $request->purpose;
                $users      = $request->users;

                $err = false;

                if($transaction_number && $value && $currency && $branch && $purpose){
                    $calc = $this->allow_complete_blance($branch,$value,$currency);
                    if(! $calc){
                        $response = response()->json(['type' => 'balance_err'],200);
                        return;
                    }

                    $chk = DB::table('branches_transactions')->where('transaction_number',$transaction_number)->count();

                    if($chk == 0){
                        $data = json_encode([
                            'purpose' => $purpose,
                            'users'   => $users,
                        ]);
                        $type = 'expenses_branch';
                        if(isset($request->type)){
                            $type = $request->type;
                            $data = $request->data;
                        }
                        // $remaining_balance = $branchesController->update_balance($id,$currency,true);

                        $usd_value = $value;
                        $exchange_rate = null;
                        if($currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = number_format((floatval($value) / $rate) , 2 , '.' , '');
                            $exchange_rate = $rate;
                        }

                        $ownerId = $request->owner_id ?? null;
                        $ownerId = is_numeric($ownerId) ? (int) $ownerId : null;

                        DB::table('branches_transactions')->insert([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'currency'     => $currency,
                            'auto_id'      => $auto_id,
                            'type'         => $type,
                            'data'         => $data,
                            'plus_minus'   => 'minus',
                            'branch'       => $branch,
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'notes'        => $notes,
                            'purpose'      => $purpose,
                            'owner_id'     => $ownerId,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        $branchesController->update_balance($branch);
                        $treasuryController->insert($transaction_number,$type,'minus',$auto_id,$data,$value,$currency,0,$branch,$notes);

                        $this->logAudit(
                            'branch_expense',
                            'branches_transactions',
                            $auto_id,
                            [
                                'branch'             => $branch,
                                'value'              => $value,
                                'currency'           => $currency,
                                'purpose'            => $purpose,
                                'type'               => $type,
                                'transaction_number' => $transaction_number,
                            ],
                            'Branch expense'
                        );

                        // Double-entry journal: branch expense.
                        // Owner-tagged purposes hit a different debit account:
                        //   owner_drawing  → Dr 3100 Owner's drawings (equity ↓)
                        //   owner_salary   → Dr 5100 Owner's salary   (expense)
                        // Plain operating expenses → Dr 5000.
                        $debitCode = '5000';
                        if ($purpose === 'owner_drawing')        $debitCode = '3100';
                        else if ($purpose === 'owner_salary')    $debitCode = '5100';
                        (new \App\Http\Controllers\journalController())->record([
                            'entry_date'         => date('Y-m-d'),
                            'kind'               => $purpose === 'owner_drawing' ? 'owner_drawing'
                                                   : ($purpose === 'owner_salary' ? 'owner_salary' : 'expense'),
                            'description'        => 'Expense ' . $value . ' ' . strtoupper($currency) . ($purpose ? ' (' . $purpose . ')' : ''),
                            'source_table'       => 'branches_transactions',
                            'source_id'          => $auto_id,
                            'transaction_number' => $transaction_number,
                            'branch_id'          => (int) $branch,
                            'lines'              => [
                                ['account_code' => $debitCode, 'dr' => (float) $value, 'cr' => 0, 'currency' => $currency, 'branch_id' => (int) $branch],
                                ['account_code' => '1000',     'dr' => 0, 'cr' => (float) $value, 'currency' => $currency, 'branch_id' => (int) $branch],
                            ],
                        ]);

                        $err = false;
                    }else{
                        $err = true;
                    }

                }else{
                    $err = true;
                }

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


    public function fix_branch(Request $request){
        $this->assertPeriodOpen(date('Y-m-d'));
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_br_code;
                
                $last_auto_id_ = DB::table('branches_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();

                $currency_exchange_rates = $dataController->currency_exchange_rates;
                
                $transaction_number = $request->transaction_number;
                $value      = $request->value;
                $currency   = $request->currency;
                $from       = $request->from_branch;
                $to         = $request->to_branch;
                $notes      = $request->notes;

                $err = false;

                if($from === $to){
                    $response = response()->json(['type' => 'err'],500);
                    return;
                }

                if($transaction_number && $value && $currency && $from && $to){
                    $calc = $this->allow_complete_blance($from,$value,$currency);
                    if(! $calc){
                        $response = response()->json(['type' => 'balance_err'],200);
                        return;
                    }

                    $chk = DB::table('branches_transactions')->where('transaction_number',$transaction_number)->count();

                    if($chk == 0){
                        $data = [
                            'type' => 'fix_branch'
                        ];
                        $purpose = $this->normalizePurpose($request->purpose, $dataController->branch_fix_purposes);

                        $exchange_rate = null;

                        $usd_value = $value;

                        if($currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = number_format((floatval($value) / $rate) , 2 , '.' , '');
                            $exchange_rate = $rate;
                        }


                        DB::table('branches_transactions')->insert([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'currency'     => $currency,
                            'auto_id'      => $auto_id,
                            'type'         => 'withdraw',
                            'data'         => json_encode($data),
                            'plus_minus'   => 'minus',
                            'branch'       => $from,
                            'notes'        => $notes,
                            'purpose'      => $purpose,
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        DB::table('branches_transactions')->insert([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'currency'     => $currency,
                            'auto_id'      => $auto_id,
                            'type'         => 'deposit',
                            'data'         => json_encode($data),
                            'plus_minus'   => 'plus',
                            'branch'       => $to,
                            'notes'        => $notes,
                            'purpose'      => $purpose,
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        $branchesController->update_balance($from);
                        $branchesController->update_balance($to);

                        $treasuryController->insert($transaction_number,'branch_withdraw','minus',$auto_id,json_encode($data),$value,$currency,0,$from,$notes);
                        $treasuryController->insert($transaction_number,'branch_deposit','plus',$auto_id,json_encode($data),$value,$currency,0,$to,$notes);

                        $this->logAudit(
                            'branch_fix',
                            'branches_transactions',
                            $auto_id,
                            [
                                'from_branch'        => $from,
                                'to_branch'          => $to,
                                'value'              => $value,
                                'currency'           => $currency,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Branch fix (transfer between branches)'
                        );

                        $err = false;
                    }else{
                        $err = true;
                    }

                }else{
                    $err = true;
                }

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

    public function transfer_branch(Request $request){
        $this->assertPeriodOpen(date('Y-m-d'));
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_br_code;
                
                $last_auto_id_ = DB::table('branches_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();
                
                $transaction_number = $request->transaction_number;
                $value          = floatval($request->value);
                $result         = floatval($request->result);
                $from           = $request->from;
                $to             = $request->to;
                $exchange_rate  = $request->exchange_rate;
                $notes          = $request->notes;
                $branch         = $request->branch;

                $err = false;

                if($transaction_number && $value && $from && $to && $exchange_rate && $branch){
                    
                    $calc = $this->allow_complete_blance($branch,$value,$from);
                    if(! $calc){
                        $response = response()->json(['type' => 'balance_err'],200);
                        return;
                    }


                    $chk = DB::table('branches_transactions')->where('transaction_number',$transaction_number)->count();

                    if($chk == 0){
                        
                        $data = null;
                        $purpose = $this->normalizePurpose($request->purpose, $dataController->branch_transfer_purposes);

                        DB::table('branches_transactions')->insert([
                            'transaction_number' => $transaction_number,
                            'value'         => $value,
                            'transfer_value'=> $result,
                            'exchange_rate' => $exchange_rate,
                            'currency'      => $from,
                            'to_currency'   => $to,
                            'auto_id'       => $auto_id,
                            'data'          => $data,
                            'type'          => 'transfer_branch',
                            'notes'         => $notes,
                            'purpose'       => $purpose,
                            'branch'        => $branch,
                            'created_by'    => auth()->user()->id,
                            'created_date'  => date('Y-m-d'),
                            'created_time'  => date('H:i:s'),
                        ]);

                        $branchesController->update_balance($branch);
                        $treasuryController->insert($transaction_number,'transfer_branch','minus',$auto_id,json_encode(['type'=>'transfer_branch' , 'exchange_rate' => $exchange_rate]),$value,$from,0,$branch,$notes);
                        $treasuryController->insert($transaction_number,'transfer_branch','plus',$auto_id,json_encode(['type' =>'transfer_branch' , 'exchange_rate' => $exchange_rate]),$result,$to,0,$branch,$notes);

                        $this->logAudit(
                            'branch_transfer_currency',
                            'branches_transactions',
                            $auto_id,
                            [
                                'branch'             => $branch,
                                'from_currency'      => $from,
                                'to_currency'        => $to,
                                'value'              => $value,
                                'result'             => $result,
                                'exchange_rate'      => $exchange_rate,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Branch currency conversion'
                        );

                        $err = false;
                    }else{
                        $err = true;
                    }
                    
                }else{
                    $err = true;
                }

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

    public function allow_complete_blance($branch,$value,$cur){
        $get = DB::table('branches')->select('balance_'.$cur)->where('id',$branch)->first();
        
        if($get){
            $balanceField = 'balance_' . $cur;
            $calc = floatval($get->$balanceField) - floatval($value);

            if($calc >= 0){
                return true;
            }else{
                return false;
            }
        }
    }

}
