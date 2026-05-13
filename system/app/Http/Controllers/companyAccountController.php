<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;
use App\Http\Controllers\treasuryController;


class companyAccountController extends Controller
{
    public function load(Request $request){

        try {

            $table = 'clients_transactions';

            if(in_array($request->type,['deposit','withdraw','transfer'])){
                $table = 'clients_transactions';
            }

            if(in_array($request->type,['branch_deposit','expenses_branch','branch_comission'])){
                $table = 'branches_transactions';
            }

            if(in_array($request->type,['transfer_branch'])){
                $table = 'branches_transactions';
            }

            if(in_array($request->type,['container_sea_withdraw'])){
                $table = 'containers_sea_fees';
            }

            if(in_array($request->type,['container_sky_withdraw'])){
                $table = 'containers_sky_fees';
            }
            
            $get = DB::table($table);
            $get = $get->orderBy('id','DESC');


            if($table === 'clients_transactions' && !in_array($request->type,['transfer'])){
                $get = $get->whereNotNull('branch');
            }
            
            if($request->currency){
                $get = $get->where('currency',$request->currency);
            }
            
            if($request->branch){
                $get = $get->where('branch',$request->branch);
            }
            
            if($request->type === 'branch_deposit'){
                $get = $get->whereNot('branch',15);
            }

            if($request->type !== 'branch_comission'){
                if($request->type){
                    $get = $get->where('type',$request->type);
                }
            }

            if($request->type === 'branch_comission'){
                $get = $get->where('branch',15);
                $get = $get->where('type','branch_deposit');
            }

            if($request->from && $request->to){
                $get = $get->whereBetween('created_date',[$request->from , $request->to]);
            }
            
            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));
            
            $branches = Cache::remember('branches_compant_accounting', env("CACHE"), function () {
                return DB::table('branches')
                    ->where('deleted', 'false')
                    ->select('id', 'name', 'name_en', 'name_zh')
                    ->get()
                    ->keyBy('id');
            });
            
            $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
                return DB::table('clients')
                    ->where('deleted', 'false')
                    ->select('id', 'name', 'code')
                    ->get()
                    ->keyBy('id');
            });
            
            $users = Cache::remember('users', env("CACHE"), function () {
                return DB::table('users')->pluck('name', 'id');
            });

            
            if($table === 'clients_transactions' && $request->type === 'transfer'){
                return view('pages.company_accounting.tables.clients_transfers',compact('get','count','users','clients','branches'));
            }
            
            if($table === 'clients_transactions'){
                return view('pages.company_accounting.tables.clients',compact('get','count','users','clients','branches'));
            }
            

            if($table === 'branches_transactions' && $request->type === 'expenses_branch'){
                return view('pages.company_accounting.tables.expenses_branch',compact('get','count','users','branches'));
            }

            if($table === 'branches_transactions' && $request->type === 'transfer_branch'){
                return view('pages.company_accounting.tables.transfer_branch',compact('get','count','users','branches'));
            }
            
            if($table === 'branches_transactions'){
                return view('pages.company_accounting.tables.branches',compact('get','count','users','branches'));
            }
            
            
            if($table === 'containers_sea_fees'){
                return view('pages.company_accounting.tables.container_sea_withdraw',compact('get','count','users','branches'));
            }
            
            if($table === 'containers_sky_fees'){
                return view('pages.company_accounting.tables.container_sky_withdraw',compact('get','count','users','branches'));
            }

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }
}
