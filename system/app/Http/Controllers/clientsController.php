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
use Illuminate\Support\Facades\Hash;

class clientsController extends Controller
{
    public function load(Request $request){

        try {
            
            $get = DB::table('clients');

            
            
            if($request->search){
                
                // $columns = Schema::getColumnListing('clients');
                // $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = ['code','name'];
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere('clients.'.$column, 'like', "%{$search}%");
                    }
                });
            }

            $show_deleted = $request->showDeleted;
            
            $get = $get->where('clients.deleted',$show_deleted);
            $get = $get->where('clients.not_active','false');

            if(auth()->user()->type === 'branch_admin'){
                $get = $get->where('clients.branch',auth()->user()->branch);
            }
            
            if($request->pending === 'true'){
                $get->whereIn('clients.id', function($query) {
                    $query->select('client_id')
                        ->from('clients_transactions')
                        ->where('status', 'pending');
                });
            }
            
            if($request->negative === 'true'){
               $get->where(function($q){
                   $q->where('clients.balance_usd','<',0)
                     ->orWhere('clients.balance_eur','<',0)
                     ->orWhere('clients.balance_den','<',0)
                     ->orWhere('clients.balance_cny','<',0);
               });
               $get->orderByRaw('GREATEST(clients.balance_usd, clients.balance_eur, clients.balance_den, clients.balance_cny) DESC');
            }
            if($request->positive === 'true'){
               $get->where(function($q){
                   $q->where('clients.balance_usd','>',0)
                     ->orWhere('clients.balance_eur','>',0)
                     ->orWhere('clients.balance_den','>',0)
                     ->orWhere('clients.balance_cny','>',0);
               });
               $get->orderByRaw('LEAST(clients.balance_usd, clients.balance_eur, clients.balance_den, clients.balance_cny) DESC');
            }

            if($request->positive !== 'true' && $request->negative !== 'true'){
                $get = $get->orderBy('clients.id','DESC');
            }

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            return view('pages.clients.table',compact('get','count','show_deleted'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function get_code(Request $request){
       
        if(!env('GET_CODE_CLIENTS')){
            return '';
        }

        try {
            $dataController = new dataController();
            $default_branches = $dataController->default_branches;

            $code = null;

            $last_code = DB::table('clients')->select('code')->where('branch',$request->branch)->orderBy('code','DESC')->limit(1)->first();

            $char = $default_branches[$request->branch]['char'];
            if($last_code){
                $exp = explode($char , $last_code->code);
                $code = $char . ($exp[1] + 1);
            }else{
                $code = $char . $default_branches[$request->branch]['number'];
            }

            return $code;
        } catch (\Throwable $th) {
            //throw $th;
        }
        
    }


    public function create(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {

                $dataController = new dataController();
                $code = $dataController->code;
                $curs = $dataController->currencies;
                $default_branches = $dataController->default_branches;

                $name     = trim($request->name);
                $pass_txt = trim($request->pass_txt);
                $email    = trim($request->email);
                $phone    = trim($request->phone);
                $type     = trim($request->type);
                $code     = trim($request->code);
                $country  = 'libya';//trim($request->country);
                $branch   = trim($request->branch);

                $branch_txt = DB::table('branches')->select('name')->where('id',$branch)->where('deleted','false')->first();

                $date = date('Y-m-d'); 
                $time = date('H:i:s'); 
                $by   = auth()->user()->id; 

                if($name  && $phone && $type && $country && $branch){

                    $chk  = DB::table('users')->where('not_active','false')->where('code',$code)->first();
                    $chk2 = DB::table('clients')->where('not_active','false')->where('code',$code)->first();

                    if($chk || $chk2){
                        $response = response()->json(['type' => 'exist'],200);
                        return;
                    }


                    $data = [
                        'code'         => $code,
                        'name'         => $name,
                        'email'        => $email,
                        'phone'        => $phone,
                        'type'         => $type,
                        'country'      => $country,
                        'branch'       => $branch,
                        'branch_txt'   => $branch_txt->name,
                        'created_date' => $date,
                        'password'     => strlen($pass_txt) > 0 ? Hash::make($pass_txt) : null,
                        'created_time' => $time,
                        'created_by'   => $by,
                        'lang'         => 'en',
                        'deleted'      => 'false',
                        'not_active'   => 'false',
                    ];

                    foreach ($curs as $key => $value) {
                        $data['balance_'.$value['code']] = 0;
                    }
                    
                    DB::table('clients')->insert($data);

                    Cache::forget('clients');
                    Cache::forget('clients_');
                    
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

                $name     = trim($request->name);
                $code     = trim($request->code);
                $email    = trim($request->email);
                $phone    = trim($request->phone);
                $pass_txt = trim($request->pass_txt);
                $type     = trim($request->type);
                $country  = 'libya';//trim($request->country);
                // $branch   = trim($request->branch);
                

                if($name && $phone && $type && $country && $code){
                    $chk = DB::table('clients')->where('code',$code)->where('id','!=',$request->id)->first();

                    if($chk){
                        $response = response()->json(['type' => 'exist'],200);
                        return;
                    }

                    $data = [
                        'name'         => $name,
                        'code'         => $code,
                        'email'        => $email,
                        'phone'        => $phone,
                        'type'         => $type,
                        'country'      => $country,
                        // 'branch'       => $branch,
                    ];

                    if (strlen($pass_txt) > 0) {
                        $data['password'] = Hash::make($pass_txt);
                    }

                    DB::table('clients')->where('id',$request->id)->update($data);

                    Cache::forget('clients');
                    Cache::forget('clients_');

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
        $this->assertCanAccessClient($request->id);

        $get = DB::table('clients')->where('id',$request->id)->first();

        if($get){
            return view('pages.clients.edit',compact('get'));
        }else{
            return response()->json(['type' => 'error'],500);
        }
    }

    public function calc_balance_api(Request $request){
        $branchesController = new branchesController();

        echo $branchesController->update_balance($request->client_id);
    }

    public function calc_balance($client_id, $currency){
        $plus  = 0; 
        $minus = 0; 

        $get = DB::table('clients_transactions')->where('status','approved')->where('client_id',$client_id)->where(function($q) use($currency){
            $q->where('currency',$currency)->orWhere('to_currency',$currency);
        })->get();

        foreach ($get as $key => $value) {
            
            if($value->type !== 'transfer'){
                if($value->plus_minus === 'plus'){
                    $plus += floatval($value->value);
                }

                if($value->plus_minus === 'minus'){
                    $minus += floatval($value->value);
                }
            }

            if($value->type === 'transfer'){
                if($value->currency === $currency){
                    $minus += floatval($value->value);
                }

                if($value->to_currency === $currency){
                    $plus += floatval($value->transfer_value);
                }
            }
        }   

        // Return a numeric. Earlier this used number_format(..., null, '.', '')
        // which both stripped decimals AND tripped a PHP 8 deprecation.
        return (float) ($plus - $minus);
    }

    public function transfer_clients(Request $request){
        $this->assertCanAccessClient($request->id);
        if (!empty($request->to_client)) {
            $this->assertCanAccessClient($request->to_client);
        }
        $this->assertPeriodOpen(date('Y-m-d'));

        try {

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_code;

                $last_auto_id_ = DB::table('clients_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $transaction_number = $request->transaction_number;
                $value      = $request->value;
                $currency   = $request->currency;
                $to_client  = $request->to_client;
                $status     = 'approved';
                $notes      = $request->notes;
                $id         = $request->id;

                $err = false;

                if($value && $currency && $to_client && $id){
                    
                    $chk = DB::table('clients_transactions')->where('transaction_number',$transaction_number)->count();

                    if($chk == 0){
                        $remaining_balance_from = $this->calc_balance($id,$currency);
                        $remaining_balance_to   = $this->calc_balance($to_client,$currency);

                        $data = json_encode([
                            'from' => $remaining_balance_from - $value,
                            'to'   => $remaining_balance_to + $value,
                            'from_client' => $id,
                            'to_client'   => $to_client,
                        ]);

                        $purpose = $this->normalizePurpose($request->purpose, $dataController->client_client_transfer_purposes);

                        $from_row_id = DB::table('clients_transactions')->insertGetId([
                            'transaction_number' => $transaction_number,
                            'value'         => $value,
                            'currency'      => $currency,
                            'auto_id'       => $auto_id,
                            'status'        => $status,
                            'remaining_balance'=> $remaining_balance_from - $value,
                            'data'          => $data,
                            'type'          => 'withdraw',
                            'plus_minus'    => 'minus',
                            'notes'         => $notes,
                            'purpose'       => $purpose,
                            'client_id'     => $id,
                            'created_by'    => auth()->user()->id,
                            'created_date'  => date('Y-m-d'),
                            'created_time'  => date('H:i:s'),
                        ]);

                        $to_row_id = DB::table('clients_transactions')->insertGetId([
                            'transaction_number' => $transaction_number,
                            'value'         => $value,
                            'currency'      => $currency,
                            'auto_id'       => $auto_id,
                            'status'        => $status,
                            'remaining_balance'=> $remaining_balance_to + $value,
                            'data'          => $data,
                            'type'          => 'deposit',
                            'plus_minus'    => 'plus',
                            'notes'         => $notes,
                            'purpose'       => $purpose,
                            'client_id'     => $to_client,
                            'created_by'    => auth()->user()->id,
                            'created_date'  => date('Y-m-d'),
                            'created_time'  => date('H:i:s'),
                        ]);

                        $this->update_balance($id);
                        $this->update_balance($to_client);

                        $this->update_remaining_balance_old_data($id);
                        $this->update_remaining_balance_old_data($to_client);

                        $this->logAudit(
                            'transfer_clients',
                            'clients_transactions',
                            $auto_id,
                            [
                                'from_client'        => $id,
                                'to_client'          => $to_client,
                                'value'              => $value,
                                'currency'           => $currency,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Transfer between clients'
                        );

                        // Two receipts — one for the from-side, one for the to-side.
                        $this->issueReceipt([
                            'source_table'       => 'clients_transactions',
                            'source_id'          => $from_row_id,
                            'transaction_number' => $transaction_number,
                            'auto_id'            => $auto_id,
                            'kind'               => 'transfer_out',
                            'currency'           => $currency,
                            'amount'             => $value,
                            'counterparty_type'  => 'client',
                            'counterparty_id'    => $id,
                            'purpose'            => $purpose,
                            'notes'              => $notes,
                        ]);
                        $this->issueReceipt([
                            'source_table'       => 'clients_transactions',
                            'source_id'          => $to_row_id,
                            'transaction_number' => $transaction_number,
                            'auto_id'            => $auto_id,
                            'kind'               => 'transfer_in',
                            'currency'           => $currency,
                            'amount'             => $value,
                            'counterparty_type'  => 'client',
                            'counterparty_id'    => $to_client,
                            'purpose'            => $purpose,
                            'notes'              => $notes,
                        ]);

                        // Double-entry journal: client-to-client transfer
                        //   Dr 2000 Client deposits (from-client)  ↓
                        //   Cr 2000 Client deposits (to-client)    ↓
                        // Both lines hit the same account code but with
                        // different counterparty_id tags. Net effect on cash
                        // is zero (no money leaves the company) — only the
                        // liability shifts from one client to another.
                        try {
                            (new \App\Http\Controllers\journalController())->record([
                                'entry_date'         => date('Y-m-d'),
                                'kind'               => 'client_to_client_transfer',
                                'description'        => 'Transfer ' . $value . ' ' . strtoupper($currency) . ' between clients',
                                'source_table'       => 'clients_transactions',
                                'source_id'          => $from_row_id,
                                'transaction_number' => $transaction_number,
                                'lines'              => [
                                    ['account_code' => '2000', 'dr' => (float) $value, 'cr' => 0, 'currency' => $currency,
                                     'counterparty_type' => 'client', 'counterparty_id' => (int) $id,
                                     'description' => 'Decrease from-client deposit'],
                                    ['account_code' => '2000', 'dr' => 0, 'cr' => (float) $value, 'currency' => $currency,
                                     'counterparty_type' => 'client', 'counterparty_id' => (int) $to_client,
                                     'description' => 'Increase to-client deposit'],
                                ],
                            ]);
                        } catch (\Throwable $ex) {
                            Log::warning('journal post failed (c2c transfer): ' . $ex->getMessage());
                        }

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
        }catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function update_balance($client_id){

        $dataController = new dataController();
        $currencies     = $dataController->currencies;


        $usd = 0;
        $eur = 0;
        $den = 0;
        $cny = 0;

        $runned = false;
        foreach ($currencies as $key => $cur) {
            $plus  = 0; 
            $minus = 0; 

            $get = DB::table('clients_transactions')->where('status','approved')->where('client_id',$client_id)->where(function($q) use($cur){
                $q->where('currency',$cur['code'])->orWhere('to_currency',$cur['code']);
            })->get();


            foreach ($get as $key => $value) {
                
                // Log::info($client_id);
                // Log::info($cur['code']);
                
                $runned = true;
                 
                if(!in_array($value->type,['transfer'])){
                    if($value->plus_minus === 'plus'){
                        $plus += floatval($value->value);
                    }

                    if($value->plus_minus === 'minus'){
                        $minus += floatval($value->value + floatval($value->commission));
                    }
                }
                
                if(in_array($value->type,['transfer'])){
                    if($value->currency === $cur['code']){
                        $minus += floatval($value->value);
                    }

                    if($value->to_currency === $cur['code']){
                        $plus += floatval($value->transfer_value);
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
               
                // DB::table('clients')->where('id',$client_id)->update([
                //     'balance_'.$cur['code'] => $plus - $minus
                // ]);
            } 
            
            // Persist raw floats. The columns are DECIMAL so the DB handles
            // precision; pre-formatting with number_format(null, ...) was a
            // PHP 8 deprecation and stripped the fractional part.
            DB::table('clients')->where('id',$client_id)->update([
                'balance_usd' => (float) $usd,
                'balance_eur' => (float) $eur,
                'balance_cny' => (float) $cny,
                'balance_den' => (float) $den,
            ]);
        }
        
    }
    
    public function search_client_balance($client_id , $from = null , $to = null){

        $dataController = new dataController();
        $currencies     = $dataController->currencies;

        $usd = 0;
        $eur = 0;
        $den = 0;
        $cny = 0;

        $runned = false;
        
        foreach ($currencies as $key => $cur) {
            $plus  = 0; 
            $minus = 0; 

            $get = DB::table('clients_transactions')->whereNull('calc')->where('status','approved');
            
            $get->whereNotNull('branch');
            $get->whereNot('type',['exp_withdraw','transfer']);

            $get->where('client_id',$client_id)->where('currency',$cur['code']);

            // $get->where('branch',14);
            // if(strlen($from)){
            //     $get->whereBetween('created_date',[$from,$to]);
            // }

            $get = $get->get();

            foreach ($get as $key => $value) {
                
                $runned = true;
                 
                if(!in_array($value->type,['transfer'])){
                    if($value->plus_minus === 'plus'){
                        $plus += floatval($value->value);
                    }

                    if($value->plus_minus === 'minus'){
                        if($value->type === 'withdraw_commission'){
                            $plus += floatval($value->value);
                        }else{
                            $minus += floatval($value->value + floatval($value->commission));
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
               
                // DB::table('clients')->where('id',$client_id)->update([
                //     'balance_'.$cur['code'] => $plus - $minus
                // ]);
            } 
            
        }
        return [$usd,$eur,$cny,$den];
    }


    public function deposit(Request $request){
        $this->assertCanAccessClient($request->id);
        $this->assertPeriodOpen(date('Y-m-d'));

        try {

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();

                $dataController = new dataController();
                $last_auto_id = $dataController->tr_code;
                
                $last_auto_id_ = DB::table('clients_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();

                $transaction_number = $request->transaction_number;
                $value      = $request->value;
                $currency   = $request->currency;
                $commission = !empty($request->commission) ? $request->commission : 0;
                $branch     = $request->branch;
                $status     = $request->status ?? 'approved';
                $notes      = $request->notes;
                $id         = $request->id;

                $err = false;

                if($value && $currency && $branch && $id){
                    
                    // $chk = DB::table('clients_transactions')->where('transaction_number',$transaction_number)->count();

                    // if($chk == 0){
                        $remaining_balance = $this->calc_balance($id,$currency,true);
                        $type  = 'deposit';
                        $data  = null;

                        if(isset($request->type)){
                            $type = $request->type;
                            $data = json_encode($request->data);
                        }
                        
                        $currency_exchange_rates = $dataController->currency_exchange_rates;

                        $exchange_rate = null;

                        $usd_value = $value;

                        if($currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = number_format((floatval($value) / $rate) , 2 , '.' , '');
                            $exchange_rate = $rate;
                        }

                        $purpose = $this->normalizePurpose($request->purpose, $dataController->client_deposit_purposes);

                        $deposit_row_id = DB::table('clients_transactions')->insertGetId([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'status'       => $status,
                            'currency'     => $currency,
                            'commission'   => $commission,
                            'auto_id'      => $auto_id,
                            'type'         => $type,
                            'data'         => $data,
                            'plus_minus'   => 'plus',
                            'branch'       => $branch,
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'remaining_balance' => $remaining_balance + $value,
                            'notes'        => $notes,
                            'purpose'      => $purpose,
                            'client_id'    => $id,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        $this->update_balance($id);
                        $branchesController->update_balance($branch,$currency);

                        $treasutry_type = 'deposit';

                        if($type === 'exp_deposit'){
                            $treasutry_type = 'exp_deposit';
                        }
                        
                        $treasury = $request->treasury ?? true;

                        if($treasury){
                            if($status !== 'pending'){
                                $treasuryController->insert($transaction_number,$treasutry_type,'plus',$auto_id,$data,$value,$currency,$commission,$branch,$notes,$id,$remaining_balance + $value);

                                if($commission > 0){
                                    $treasuryController->insert($transaction_number,'deposit_commission','plus',$auto_id,json_encode(['type'=>'commission']),$commission,$currency,0,15,$notes,$id,0);
                                }
                            }
                        }

                        $this->logAudit(
                            'deposit',
                            'clients_transactions',
                            $auto_id,
                            [
                                'client_id'          => $id,
                                'value'              => $value,
                                'currency'           => $currency,
                                'commission'         => $commission,
                                'branch'             => $branch,
                                'status'             => $status,
                                'type'               => $type,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Client deposit'
                        );

                        $this->issueReceipt([
                            'source_table'       => 'clients_transactions',
                            'source_id'          => $deposit_row_id,
                            'transaction_number' => $transaction_number,
                            'auto_id'            => $auto_id,
                            'kind'               => 'deposit',
                            'currency'           => $currency,
                            'amount'             => $value,
                            'counterparty_type'  => 'client',
                            'counterparty_id'    => $id,
                            'branch_id'          => $branch,
                            'purpose'            => $purpose,
                            'notes'              => $notes,
                        ]);

                        // Double-entry journal: client deposit
                        //   Dr 1000 Cash on hand      (asset ↑)
                        //   Cr 2000 Client deposits   (liability ↑)
                        // We only post when status='approved' so a future
                        // pending-deposit flow doesn't pollute the trial
                        // balance with un-effective rows.
                        if ($status === 'approved') {
                            try {
                                (new \App\Http\Controllers\journalController())->record([
                                    'entry_date'         => date('Y-m-d'),
                                    'kind'               => 'client_deposit',
                                    'description'        => 'Client deposit ' . $value . ' ' . strtoupper($currency),
                                    'source_table'       => 'clients_transactions',
                                    'source_id'          => $deposit_row_id,
                                    'transaction_number' => $transaction_number,
                                    'branch_id'          => (int) $branch,
                                    'lines'              => [
                                        ['account_code' => '1000', 'dr' => (float) $value, 'cr' => 0, 'currency' => $currency,
                                         'counterparty_type' => 'client', 'counterparty_id' => (int) $id, 'branch_id' => (int) $branch],
                                        ['account_code' => '2000', 'dr' => 0, 'cr' => (float) $value, 'currency' => $currency,
                                         'counterparty_type' => 'client', 'counterparty_id' => (int) $id, 'branch_id' => (int) $branch],
                                    ],
                                ]);
                            } catch (\Throwable $ex) {
                                Log::warning('journal post failed (client deposit): ' . $ex->getMessage());
                            }
                        }

                        // Auto-register a prepayment row when the operator
                        // tagged this deposit as a prepayment. Doing it inside
                        // the same transaction means the prepayments report is
                        // accurate the moment the deposit lands.
                        if ($purpose === 'prepayment_received') {
                            try {
                                DB::table('prepayments')->insert([
                                    'client_id'             => $id,
                                    'source_transaction_id' => $deposit_row_id,
                                    'currency'              => $currency,
                                    'original_amount'       => (float) $value,
                                    'applied_amount'        => 0,
                                    'remaining_amount'      => (float) $value,
                                    'status'                => 'open',
                                    'received_date'         => date('Y-m-d'),
                                    'created_by_user_id'    => auth()->user()->id,
                                    'created_at'            => date('Y-m-d H:i:s'),
                                    'updated_at'            => date('Y-m-d H:i:s'),
                                ]);
                            } catch (\Throwable $e) {
                                Log::warning('prepayment auto-register failed: ' . $e->getMessage());
                            }
                        }

                        $err = false;
                    // }else{
                    //     $err = true;
                    // }
                    
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

    public function withdraw(Request $request){
        $this->assertCanAccessClient($request->id);
        $this->assertPeriodOpen(date('Y-m-d'));

        try {

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_code;
                
                $last_auto_id_ = DB::table('clients_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();

                $transaction_number = $request->transaction_number;
                $value      = $request->value;
                $currency   = $request->currency;
                $commission = !empty($request->commission) ? $request->commission : 0;
                $branch     = $request->branch;
                $notes      = $request->notes;
                $id         = $request->id;
                $status     = $request->status ?? 'approved';

                $err = false;

                if($value && $currency && $id){
                    
                    // $chk = DB::table('clients_transactions')->where('transaction_number',$transaction_number)->count();

                    // if($chk == 0){
                        $remaining_balance = $this->calc_balance($id,$currency,true);

                        $type  = 'withdraw';
                        $data  = null;

                        if(isset($request->type)){
                            $type = $request->type;
                            $data = json_encode($request->data);
                        }

                        $currency_exchange_rates = $dataController->currency_exchange_rates;


                        $exchange_rate = null;

                        $usd_value = $value;

                        if($currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = number_format((floatval($value) / $rate) , 2 , '.' , '');
                            $exchange_rate = $rate;
                        }


                        $purpose = $this->normalizePurpose($request->purpose, $dataController->client_withdraw_purposes);

                        $withdraw_row_id = DB::table('clients_transactions')->insertGetId([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'currency'     => $currency,
                            'status'       => $status,
                            'auto_id'      => $auto_id,
                            'commission'   => $commission,
                            'remaining_balance' => $remaining_balance - $value - $commission,
                            'type'         => $type,
                            'data'         => $data,
                            'plus_minus'   => 'minus',
                            'calc'         => $request->old_balance === "true" ? 'false':null,
                            'branch'       => $branch,
                            'notes'        => $notes,
                            'purpose'      => $purpose,
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'client_id'    => $id,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        $this->update_balance($id);

                        if($branch){
                            $branchesController->update_balance($branch,$currency);
                        }

                        $treasutry_type = 'withdraw';

                        if($type === 'exp_withdraw'){
                            $treasutry_type = 'exp_withdraw';
                        }
                        
                        $treasury = $request->treasury ?? true;

                        if($treasury && $treasutry_type !== 'exp_withdraw'){
                            if($status !== 'pending'){
                                $treasuryController->insert($transaction_number,$treasutry_type,'minus',$auto_id,$data,$value,$currency,$commission,$branch,$notes,$id,$remaining_balance - $value - $commission);

                                if($commission > 0){
                                    $treasuryController->insert($transaction_number,'withdraw_commission','plus',$auto_id,json_encode(['type'=>'commission']),$commission,$currency,0,15,$notes,$id,0);
                                }
                            }
                        }

                        $this->logAudit(
                            'withdraw',
                            'clients_transactions',
                            $auto_id,
                            [
                                'client_id'          => $id,
                                'value'              => $value,
                                'currency'           => $currency,
                                'commission'         => $commission,
                                'branch'             => $branch,
                                'status'             => $status,
                                'type'               => $type,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Client withdraw'
                        );

                        $this->issueReceipt([
                            'source_table'       => 'clients_transactions',
                            'source_id'          => $withdraw_row_id,
                            'transaction_number' => $transaction_number,
                            'auto_id'            => $auto_id,
                            'kind'               => 'withdraw',
                            'currency'           => $currency,
                            'amount'             => $value,
                            'counterparty_type'  => 'client',
                            'counterparty_id'    => $id,
                            'branch_id'          => $branch,
                            'purpose'            => $purpose,
                            'notes'              => $notes,
                        ]);

                        // Double-entry journal: client withdraw
                        //   Dr 2000 Client deposits   (liability ↓)
                        //   Cr 1000 Cash on hand      (asset ↓)
                        if ($status === 'approved') {
                            try {
                                (new \App\Http\Controllers\journalController())->record([
                                    'entry_date'         => date('Y-m-d'),
                                    'kind'               => 'client_withdraw',
                                    'description'        => 'Client withdraw ' . $value . ' ' . strtoupper($currency),
                                    'source_table'       => 'clients_transactions',
                                    'source_id'          => $withdraw_row_id,
                                    'transaction_number' => $transaction_number,
                                    'branch_id'          => (int) $branch,
                                    'lines'              => [
                                        ['account_code' => '2000', 'dr' => (float) $value, 'cr' => 0, 'currency' => $currency,
                                         'counterparty_type' => 'client', 'counterparty_id' => (int) $id, 'branch_id' => (int) $branch],
                                        ['account_code' => '1000', 'dr' => 0, 'cr' => (float) $value, 'currency' => $currency,
                                         'counterparty_type' => 'client', 'counterparty_id' => (int) $id, 'branch_id' => (int) $branch],
                                    ],
                                ]);
                            } catch (\Throwable $ex) {
                                Log::warning('journal post failed (client withdraw): ' . $ex->getMessage());
                            }
                        }

                        $err = false;
                    // }else{
                    //     $err = true;
                    // }

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

    public function withdraw_commission(Request $request){
        $this->assertCanAccessClient($request->id);
        $this->assertPeriodOpen(date('Y-m-d'));

        try {

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_code;
                
                $last_auto_id_ = DB::table('clients_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $branchesController = new branchesController();

                $transaction_number = $request->transaction_number;
                $currency   = $request->currency;
                $commission = !empty($request->commission) ? $request->commission : 0;
                $value      = $commission;
                $branch     = $request->branch;
                $notes      = $request->notes;
                $id         = $request->id;
                $status     = $request->status ?? 'approved';

                $err = false;

                if($currency && $id){
                    
                    // $chk = DB::table('clients_transactions')->where('transaction_number',$transaction_number)->count();

                    // if($chk == 0){
                        $remaining_balance = $this->calc_balance($id,$currency,true);

                        $type  = 'withdraw_commission';
                        $data  = null;

                        if(isset($request->type)){
                            $type = $request->type;
                            $data = json_encode($request->data);
                        }

                        $currency_exchange_rates = $dataController->currency_exchange_rates;


                        $exchange_rate = null;

                        $usd_value = $value;

                        if($currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = number_format((floatval($value) / $rate) , 2 , '.' , '');
                            $exchange_rate = $rate;
                        }


                        $commission_row_id = DB::table('clients_transactions')->insertGetId([
                            'transaction_number' => $transaction_number,
                            'value'        => $value,
                            'currency'     => $currency,
                            'status'       => $status,
                            'auto_id'      => $auto_id,
                            'remaining_balance' => $remaining_balance - $value - $commission,
                            'type'         => $type,
                            'data'         => $data,
                            'plus_minus'   => 'minus',
                            'branch'       => 15,
                            'notes'        => $notes,
                            'purpose'      => 'commission',
                            'exchange_rate'=> $exchange_rate,
                            'usd_value'    => $usd_value,
                            'client_id'    => $id,
                            'created_by'   => auth()->user()->id,
                            'created_date' => date('Y-m-d'),
                            'created_time' => date('H:i:s'),
                        ]);

                        $this->update_balance($id);

                        if($branch){
                            $branchesController->update_balance($branch,$currency);
                        }

                        // $treasutry_type = 'withdraw_commission';
                        
                        // $treasury = $request->treasury ?? true;

                        // if($treasury && $treasutry_type !== 'exp_withdraw'){
                        //     if($status !== 'pending'){
                        //         $treasuryController->insert($transaction_number,$treasutry_type,'minus',$auto_id,$data,$value,$currency,$commission,$branch,$notes,$id,$remaining_balance - $value - $commission);

                        //         if($commission > 0){
                        //             $treasuryController->insert($transaction_number,'withdraw_commission','plus',$auto_id,json_encode(['type'=>'commission']),$commission,$currency,0,$branch,$notes,$id,0);
                        //         }
                        //     }
                        // }

                        $this->logAudit(
                            'withdraw_commission',
                            'clients_transactions',
                            $auto_id,
                            [
                                'client_id'          => $id,
                                'value'              => $value,
                                'currency'           => $currency,
                                'status'             => $status,
                                'transaction_number' => $transaction_number,
                            ],
                            'Withdraw commission'
                        );

                        $this->issueReceipt([
                            'source_table'       => 'clients_transactions',
                            'source_id'          => $commission_row_id,
                            'transaction_number' => $transaction_number,
                            'auto_id'            => $auto_id,
                            'kind'               => 'commission',
                            'currency'           => $currency,
                            'amount'             => $value,
                            'counterparty_type'  => 'client',
                            'counterparty_id'    => $id,
                            'branch_id'          => 15,
                            'purpose'            => 'commission',
                            'notes'              => $notes,
                        ]);

                        // Double-entry journal: commission charge
                        //   Dr 2000 Client deposits   (liability ↓)
                        //   Cr 4000 Commission revenue
                        if ($status === 'approved') {
                            try {
                                (new \App\Http\Controllers\journalController())->record([
                                    'entry_date'         => date('Y-m-d'),
                                    'kind'               => 'commission',
                                    'description'        => 'Client commission ' . $value . ' ' . strtoupper($currency),
                                    'source_table'       => 'clients_transactions',
                                    'source_id'          => $commission_row_id,
                                    'transaction_number' => $transaction_number,
                                    'branch_id'          => (int) ($branch ?: 15),
                                    'lines'              => [
                                        ['account_code' => '2000', 'dr' => (float) $value, 'cr' => 0, 'currency' => $currency,
                                         'counterparty_type' => 'client', 'counterparty_id' => (int) $id],
                                        ['account_code' => '4000', 'dr' => 0, 'cr' => (float) $value, 'currency' => $currency,
                                         'counterparty_type' => 'client', 'counterparty_id' => (int) $id],
                                    ],
                                ]);
                            } catch (\Throwable $ex) {
                                Log::warning('journal post failed (commission): ' . $ex->getMessage());
                            }
                        }

                        $err = false;
                    // }else{
                    //     $err = true;
                    // }

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

    public function transfer(Request $request){
        $this->assertCanAccessClient($request->id);
        $this->assertPeriodOpen(date('Y-m-d'));

        try {

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $treasuryController = new treasuryController();
                $dataController = new dataController();
                $last_auto_id = $dataController->tr_code;
                
                $last_auto_id_ = DB::table('clients_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;


                $transaction_number = $request->transaction_number;
                $value          = floatval($request->value);
                $result         = floatval($request->result);
                $from           = $request->from;
                $to             = $request->to;
                $exchange_rate  = $request->exchange_rate;
                $notes          = $request->notes;
                $id             = $request->id;

                $err = false;

                if($transaction_number && $value && $from && $to && $exchange_rate && $id){
                    
                    $chk = DB::table('clients_transactions')->where('transaction_number',$transaction_number)->count();

                    if($chk == 0){
                        $remaining_balance_from = $this->calc_balance($id,$from,null,true);
                        $remaining_balance_to   = $this->calc_balance($id,$to,null,true);

                        $data = json_encode([
                            'from' => $remaining_balance_from - $value,
                            'to'   => $remaining_balance_to + $result,
                        ]);

                        $purpose = $this->normalizePurpose($request->purpose, $dataController->client_transfer_purposes);

                        $transfer_row_id = DB::table('clients_transactions')->insertGetId([
                            'transaction_number' => $transaction_number,
                            'value'         => $value,
                            'transfer_value'=> $result,
                            'exchange_rate' => $exchange_rate,
                            'currency'      => $from,
                            'to_currency'   => $to,
                            'auto_id'       => $auto_id,
                            'status'        => 'pending',
                            'data'          => $data,
                            'type'          => 'transfer',
                            'notes'         => $notes,
                            'purpose'       => $purpose,
                            'client_id'     => $id,
                            'created_by'    => auth()->user()->id,
                            'created_date'  => date('Y-m-d'),
                            'created_time'  => date('H:i:s'),
                        ]);

                        // $this->update_balance($id);

                        // $treasuryController->insert($transaction_number,'withdraw','minus',$auto_id,json_encode(['type'=>'transfer' , 'exchange_rate' => $exchange_rate]),$value,$from,0,null,$notes,$id,$remaining_balance_from - $value);
                        // $treasuryController->insert($transaction_number,'deposit','plus',$auto_id,json_encode(['type'=>'transfer' , 'exchange_rate' => $exchange_rate]),$result,$to,0,null,$notes,$id,$remaining_balance_to + $result);

                        $this->logAudit(
                            'transfer_currency',
                            'clients_transactions',
                            $auto_id,
                            [
                                'client_id'          => $id,
                                'from_currency'      => $from,
                                'to_currency'        => $to,
                                'value'              => $value,
                                'result'             => $result,
                                'exchange_rate'      => $exchange_rate,
                                'purpose'            => $purpose,
                                'transaction_number' => $transaction_number,
                            ],
                            'Client currency transfer (pending approval)'
                        );

                        $this->issueReceipt([
                            'source_table'       => 'clients_transactions',
                            'source_id'          => $transfer_row_id,
                            'transaction_number' => $transaction_number,
                            'auto_id'            => $auto_id,
                            'kind'               => 'transfer',
                            'currency'           => $from,
                            'amount'             => $value,
                            'counterparty_type'  => 'client',
                            'counterparty_id'    => $id,
                            'purpose'            => $purpose,
                            'notes'              => $notes,
                            'status'             => 'pending', // defer receipt until approval
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

    public function get_client_data(Request $request){
        $this->assertCanAccessClient($request->id);

        try {
            $get = DB::table('clients')->select(['code','name'])->where('deleted','false')->where('id',$request->id)->first();

            if($get){
                return response()->json($get ,200);
            }else{
                return response()->json(['type' => 'error'],500);
            }

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function del_transaction(Request $request){
        if (!in_array(auth()->user()->type, ['admin'], true)) {
            abort(403, 'Unauthorized');
        }

        try {
            $get = DB::table('clients_transactions')->where('id',$request->id)->first();

            $branchesController = new branchesController();

            if($get){
                $cur       = $get->currency;
                $branch    = $get->branch;
                $client_id = floatval($get->client_id);

                $chk_data = json_decode($get->data,true);

                if(isset($chk_data['from_client']) && isset($chk_data['to_client'])){
                    DB::table('clients_transactions')->where('transaction_number',$get->transaction_number)->delete();
                    $this->update_balance($chk_data['from_client']);
                    $this->update_remaining_balance_old_data($chk_data['from_client']);
                    $this->update_balance($chk_data['to_client']);
                    $this->update_remaining_balance_old_data($chk_data['to_client']);

                }else{
                    DB::table('treasury_transactions')->where('type',$get->type)->where('transaction_number',$get->transaction_number)->where('client_id',$get->client_id)->delete();

                    if($get->type === 'deposit'){
                        DB::table('treasury_transactions')->where('type','deposit_commission')->where('transaction_number',$get->transaction_number)->where('client_id',$get->client_id)->delete();
                    }
                    DB::table('clients_transactions')->where('id',$request->id)->delete();

                    $branchesController->update_balance($branch,$cur);

                    $this->update_balance($client_id);
                    $this->update_remaining_balance_old_data($client_id);
                }

                // Record the deletion AFTER it succeeds. We pass the original
                // row contents so we can reconstruct it if a dispute arises.
                $this->logAudit(
                    'transaction_delete',
                    'clients_transactions',
                    $get->id,
                    [
                        'transaction_number' => $get->transaction_number,
                        'auto_id'            => $get->auto_id,
                        'type'               => $get->type,
                        'plus_minus'         => $get->plus_minus,
                        'value'              => $get->value,
                        'currency'           => $get->currency,
                        'commission'         => $get->commission,
                        'branch'             => $get->branch,
                        'client_id'          => $get->client_id,
                        'status'             => $get->status,
                        'notes'              => $get->notes,
                        'created_by'         => $get->created_by,
                        'created_date'       => $get->created_date,
                        'created_time'       => $get->created_time,
                    ],
                    'Client transaction deleted'
                );

            }else{
                return response()->json(['type' => 'error'],500);
            }

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function update_remaining_balance_old_data($client_id){

        $dataController = new dataController();
        $currencies     = $dataController->currencies;

        $runned = false;
        foreach ($currencies as $key => $cur) {
            $plus  = 0; 
            $minus = 0; 

            $get = DB::table('clients_transactions')->where('status','approved')->where('client_id',$client_id)->where(function($q) use($cur){
                $q->where('currency',$cur['code'])->orWhere('to_currency',$cur['code']);
            })->get();

            foreach ($get as $key => $value) {
                
                $runned = true;
                
                if(!in_array($value->type,['transfer'])){
                    if($value->plus_minus === 'plus'){
                        $plus += floatval($value->value);
                    }

                    if($value->plus_minus === 'minus'){
                        $minus += floatval($value->value + floatval($value->commission));
                    }
                }
                
                if(in_array($value->type,['transfer'])){
                    if($value->currency === $cur['code']){
                        $minus += floatval($value->value);
                    }

                    if($value->to_currency === $cur['code']){
                        $plus += floatval($value->transfer_value);
                    }
                }
            
                if ($value->type === 'transfer') {
                    $data_ = json_decode($value->data, true);

                    if ($value->currency === $cur['code']) {
                        $data_['from'] = $plus - $minus;
                    }

                    if ($value->to_currency === $cur['code']) {
                        $data_['to'] = $plus - $minus;
                    }

                    DB::table('clients_transactions')
                        ->where('id', $value->id)
                        ->update([
                            'data' => json_encode($data_)
                        ]);
                } else {
                    DB::table('clients_transactions')
                        ->where('id', $value->id)
                        ->update([
                            'remaining_balance' => $plus - $minus
                        ]);
                }
            } 
        }
    }
}
