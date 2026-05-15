<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\clientsController;


class treasuryController extends Controller
{
    public function load(Request $request){

        try {
            
            $get = DB::table('treasury_transactions');
            // $get = $get->orderBy('id','DESC');

            $date     = $request->date;
            $date2    = $request->date2;
            $currency = $request->currency;
            $branch   = $request->branch;

            if($request->currency){
                $get = $get->where('currency',$request->currency);
            }
            
            if (in_array(auth()->user()->type , ['branch_admin'])) {
                $get = $get->where('branch', auth()->user()->branch);
            }else{
                if($request->branch){
                    $get = $get->where('branch',$request->branch);

                    if($request->branch == 12 && $request->currency == 'cny'){
                        $get = $get->whereNotIn('type',['withdraw']);
                    }
                }
            }

            

            if($request->date2){
                $get = $get->whereBetween('created_date',[$request->date,$request->date2]);
            }

            $branches = Cache::remember('branches_compant_accounting', env("CACHE"), function () {
                return DB::table('branches')
                    // ->where('deleted', 'false')
                    ->select('id', 'name', 'name_en', 'name_zh','deleted')
                    ->get()
                    ->keyBy('id');
            });
            
            $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
                return DB::table('clients')
                    // ->where('deleted', 'false')
                    ->select('id', 'name', 'deleted','code')
                    ->get()
                    ->keyBy('id');
            });
            
            $users = Cache::remember('users', env("CACHE"), function () {
                return DB::table('users')->pluck('name', 'id');
            });

            $count = $get->count();
            $get   = $get->get();

            return view('pages.treasury.table',compact('get','count','users','clients','branches','date','date2','branch','currency'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function load_balance(Request $request){
        try {
            $date   = $request->date;

            if (in_array(auth()->user()->type , ['branch_admin'])) {
                $branch = auth()->user()->branch;
            }else{
                $branch = $request->branch;
            }
           
            return view('pages.treasury.opening_balance',compact('date','branch'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    static public function save_treasury(){ // For Cron jobs
        // \Log::info('Hello from Laravel 12!');
    }

    /**
     * Future-proof helper. Writes a cash movement to BOTH treasury_transactions
     * and branches_transactions inside one DB transaction, and bumps the
     * branch balance column. This is the only way to guarantee the dual-ledger
     * invariant — every cash event lives in both ledgers, with matching
     * transaction_number, so the daily journal, cash flow, trial balance,
     * and branch.balance_* always agree.
     *
     * Callers should pass:
     *   transaction_number, type, plus_minus ('plus'|'minus'), auto_id,
     *   value, currency, branch, notes, purpose, data (json),
     *   client_id (nullable), remaining_balance (nullable)
     *
     * Legacy callers that already write branches_transactions themselves
     * (clientsController::deposit etc.) should keep using treasuryController
     * ::insert until the journal layer migration replaces them in bulk.
     */
    public function recordCashMovement(array $p): int
    {
        $required = ['transaction_number', 'type', 'plus_minus', 'value', 'currency', 'branch'];
        foreach ($required as $k) {
            if (!isset($p[$k]) || $p[$k] === '' || $p[$k] === null) {
                throw new \InvalidArgumentException("recordCashMovement: missing $k");
            }
        }
        $plusMinus = $p['plus_minus'];
        if (!in_array($plusMinus, ['plus', 'minus'], true)) {
            throw new \InvalidArgumentException("recordCashMovement: plus_minus must be 'plus' or 'minus'");
        }
        $auto = $p['auto_id'] ?? (((int) DB::table('branches_transactions')->where('branch', $p['branch'])->max('auto_id')) + 1);

        $brTxnId = null;
        DB::transaction(function () use ($p, $plusMinus, $auto, &$brTxnId) {
            $row = [
                'transaction_number' => $p['transaction_number'],
                'value'        => (float) $p['value'],
                'currency'     => $p['currency'],
                'auto_id'      => $auto,
                'type'         => $p['type'],
                'data'         => $p['data'] ?? null,
                'plus_minus'   => $plusMinus,
                'branch'       => $p['branch'],
                'notes'        => $p['notes'] ?? null,
                'purpose'      => $p['purpose'] ?? null,
                'created_by'   => auth()->user()?->id,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
            ];
            $brTxnId = DB::table('branches_transactions')->insertGetId($row);

            $tx = $row + [
                'commission'   => $p['commission'] ?? 0,
                'remaining_balance' => $p['remaining_balance'] ?? 0,
                'client_id'    => $p['client_id'] ?? null,
            ];
            unset($tx['purpose']); // treasury_transactions has no purpose column
            DB::table('treasury_transactions')->insert($tx);

            $col = 'balance_' . $p['currency'];
            if ($plusMinus === 'plus') {
                DB::table('branches')->where('id', $p['branch'])->increment($col, (float) $p['value']);
            } else {
                DB::table('branches')->where('id', $p['branch'])->decrement($col, (float) $p['value']);
            }
        });

        return $brTxnId;
    }

    public function insert($transaction_number,$type,$plus_minus,$auto_id,$data,$value,$currency,$commission,$branch = null,$notes,$client_id = null,$remaining_balance = 0){
        try {

            DB::transaction(function () use ($transaction_number,$type,$plus_minus,$auto_id,$data,$value,$currency,$commission,$branch,$notes,$client_id,$remaining_balance ) {

                if($transaction_number && $value && $currency){
                    
                    $data = [
                        'transaction_number' => $transaction_number,
                        'value'        => $value,
                        'commission'   => $commission,
                        'auto_id'      => $auto_id,
                        'data'         => $data,
                        'type'         => $type,
                        'plus_minus'   => $plus_minus,
                        'branch'       => $branch,
                        'remaining_balance' => $remaining_balance,
                        'notes'        => $notes,
                        'client_id'    => $client_id,
                        'currency'     => $currency,
                        'created_by'   => auth()->user()->id,
                        'created_date' => date('Y-m-d'),
                        'created_time' => date('H:i:s'),
                    ];

                    DB::table('treasury_transactions')->insert($data);
                }
            });

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function get_totals(Request $request){
        try {

            $clientController = new clientsController();

            $client_balance = $clientController->calc_balance($request->client_id, $request->currency);
            
            $treasury = DB::table('treasury_transactions')->where('currency',$request->currency)->where('branch',$request->branch)->get();

            $input  = 0;
            $output = 0;

            foreach ($treasury as $key => $item) {
                if ($item->plus_minus === 'plus') {
                    $input  += $item->value;
                } else {
                    $output += $item->value;
                }
            }

            $total = ($input - $output);
            
            return response()->json([$total , $client_balance],200);

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }
}
