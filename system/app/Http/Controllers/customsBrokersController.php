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


class customsBrokersController extends Controller
{
    public function load(Request $request){
        try {
            
            $get = DB::table('customs_brokers');
            $get = $get->orderBy('id','DESC');
            
            if($request->search){
                
                $columns = Schema::getColumnListing('customs_brokers');
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

            return view('pages.customs_brokers.table',compact('get','count','show_deleted'));
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

                $name = trim($request->name);
                $type = trim($request->type);

                $date = date('Y-m-d'); 
                $time = date('H:i:s'); 
                $by   = auth()->user()->id; 

                if($name){
                    $data = [
                        'name'         => $name,
                        'type'         => $type,
                        'created_date' => $date,
                        'created_time' => $time,
                        'created_by'   => $by,
                        'deleted'      => 'false',
                        'not_active'   => 'false',
                    ];

                     foreach ($curs as $key => $value) {
                        $data['balance_'.$value['code']] = 0;
                    }

                    DB::table('customs_brokers')->insert($data);

                    Cache::forget('customs_brokers');
                    
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
                $type = trim($request->type);

                if($name ){
                    $data = [
                        'name'  => $name,
                        'type'  => $type,
                    ];

                    DB::table('customs_brokers')->where('id',$request->id)->update($data);

                    Cache::forget('customs_brokers');

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
        $get = DB::table('customs_brokers')->where('id',$request->id)->first();

        if($get){
            return view('pages.customs_brokers.edit',compact('get'));
        }else{
            return response()->json(['type' => 'error'],500);
        }
    }

    public function withdraw(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $dataController   = new dataController();
                
                $container_id       = $request->container_id;
                $container_number   = $request->container_number;
                $transaction_number = $request->transaction_number;
                $supplier_id        = $request->broker_id;
                $plus_minus         = $request->plus_minus;
                $pay_for            = $request->pay_for;
                $branch             = $request->branch;
                $value              = $request->value;
                $type               = $request->type;
                $currency           = $request->currency;
                $sky_sea            = $request->sky_sea;
 
                $created_date = date('Y-m-d');
                $created_time = date('H:i:s');
                $created_by   = auth()->user()->id;

                $last_auto_id = $dataController->supplier_code;
                
                $last_auto_id_ = DB::table('customs_brokers_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;
                
                DB::table('customs_brokers_transactions')->insert([
                    'container_id'       => $container_id,
                    'container_number'   => $container_number,
                    'transaction_number' => $transaction_number,
                    'broker_id'          => $supplier_id,
                    'value'              => $value,
                    'auto_id'            => $auto_id,
                    'currency'           => $currency,
                    'plus_minus'         => $plus_minus,
                    'branch'             => $branch,
                    'from_value'         => $value,
                    'from_currency'      => $currency,
                    'pay_for'            => $pay_for,
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
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                
                $dataController   = new dataController();
                $treasuryController   = new treasuryController();
                $branchesController   = new branchesController();
                $currency_exchange_rates = $dataController->currency_exchange_rates;

                $last_auto_id = $dataController->supplier_code;
                
                $last_auto_id_ = DB::table('customs_brokers_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
                $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;

                $transaction_number = $request->transaction_number;
                $broker_id         = $request->broker_id;
                $plus_minus         = $request->plus_minus;
                $pay_for            = $request->pay_for ?? null;
                $sky_sea            = $request->sky_sea ?? null;
                $type               = $request->type ?? 'deposit';
                $value              = $request->value;
                $branch             = $request->branch;
                $notes              = $request->notes;
                $from_value         = $request->value;
                $currency           = $request->currency;
                $container_id       = $request->container_id;
                $container_number   = $request->container_number;

                $calc = $branchesController->allow_complete_blance($branch,$value,$currency);
                if(! $calc){
                    $response = response()->json(['type' => 'balance_err'],200);
                    return;
                }

                $rate = 0;

                if($currency !== 'usd'){
                    $rate = floatval($currency_exchange_rates[$currency]);
                    // $value = floatval($value) / $rate;
                }
                
                $created_date = date('Y-m-d');
                $created_time = date('H:i:s');
                $created_by   = auth()->user()->id;

                DB::table('customs_brokers_transactions')->insert([
                    'transaction_number' => $transaction_number,
                    'container_id'       => $container_id,
                    'container_number'   => $container_number,
                    'broker_id'          => $broker_id,
                    'sky_sea'            => $sky_sea,
                    'branch'             => $branch,
                    'exchange_rate'      => $rate,
                    'value'              => $value,
                    'auto_id'            => $auto_id,
                    'pay_for'            => $pay_for,
                    'currency'           => $currency,
                    'from_value'         => $from_value,
                    'from_currency'      => $currency,
                    'notes'              => $notes,
                    'plus_minus'         => 'plus',
                    'type'               => $type,
                    'created_date'       => $created_date,
                    'created_time'       => $created_time,
                    'created_by'         => $created_by,
                ]);

                $this->update_balance($broker_id);

                $treasuryData = json_encode([
                    'broker_id' => $broker_id
                ]);

                if($branch){
                    $type = $request->type ?? 'customs_deposit';
                    $branchesController->update_balance($branch,$currency);
                    $treasuryController->insert($transaction_number,$type,'minus',$auto_id,$treasuryData,$from_value,$currency,0,$branch,$notes,null);
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

    public function update_balance($supplier_id){
        $dataController = new dataController();
        $currencies     = $dataController->currencies;

        foreach ($currencies as $key => $cur) {
            $plus  = 0;
            $minus = 0;

            $get = DB::table('customs_brokers_transactions')->where('currency',$cur['code'])->where('broker_id',$supplier_id)->get();

            foreach ($get as $key => $value) {
                if($value->plus_minus === 'plus'){
                    $plus += floatval($value->value);
                }else{
                    $minus += floatval($value->value);
                }
            }


            DB::table('customs_brokers')->where('id',$supplier_id)->update([
                'balance_'.$cur['code'] => $plus - $minus
            ]);

        }
    }


    public function reports(Request $request){
        $get = DB::table('customs_brokers_transactions');
        
        // $get = $get->where('type','deposit');
        $get = $get->where('broker_id',$request->supplier_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        $broker = $request->supplier_id;

        return view('pages.reports.customs_brokers.reports_table',compact('get','users','broker'));
    }
}
