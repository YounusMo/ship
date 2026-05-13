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
