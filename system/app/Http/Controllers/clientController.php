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
use Illuminate\Support\Facades\Auth;

class clientController extends Controller
{
    
    public function load_transactions(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;

        if($currency){
            $get = $get->where(function($q) use($currency){
                $q->where('currency',$currency)->orWhere('to_currency',$currency);
            });
        }

        if($request->from && $request->to){
            $get = $get->whereBetween('created_date',[$request->from,$request->to]);    
        }
        
        $get->where('status','approved');

        $client_id = Auth::guard('client')->user()->id;

        $get = $get->where('client_id',$client_id);
        $get = $get->paginate(env('PAGEVIEW'));

        $client = DB::table('clients')->where('id',$client_id)->first();
        
        return view('pages.client.transactions.table',compact('get','currency','client'));
    }


    public function load_sea_containers(Request $request){
        try {

            // Scope to containers the authenticated client actually has cargo
            // in. Without this filter a logged-in client could enumerate every
            // container in the system — including counterparties' names,
            // codes, totals — by paginating freely. The view ties container
            // membership to store_out_sea (client_id, container_id), so we
            // mirror that here.
            $client_id = Auth::guard('client')->user()->id;
            $get = DB::table('containers_sea')
                ->whereIn('id', function ($q) use ($client_id) {
                    $q->select('container_id')
                      ->from('store_out_sea')
                      ->where('client_id', $client_id)
                      ->whereNotNull('container_id');
                })
                ->orderBy('id','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            return view('pages.client.shipping.sea.containers.table',compact('get','count'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function load_sky_containers(Request $request){
        try {

            $client_id = Auth::guard('client')->user()->id;
            $get = DB::table('containers_sky')
                ->whereIn('id', function ($q) use ($client_id) {
                    $q->select('container_id')
                      ->from('store_out_sky')
                      ->where('client_id', $client_id)
                      ->whereNotNull('container_id');
                })
                ->orderBy('id','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            return view('pages.client.shipping.sky.containers.table',compact('get','count'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function print_reports(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;

        if($currency){
            $get = $get->where(function($query) use ($currency) {
                $query->where('currency', $currency)
                      ->orWhere('to_currency', $currency);
            });
        }

        if($request->from && $request->to){
            $get = $get->whereBetween('created_date',[$request->from,$request->to]);    
        }

        $client_id = Auth::guard('client')->user()->id;


        $get = $get->where('status','approved');
        $get = $get->where('client_id',$client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        // $users = Cache::remember('users', env("CACHE"), function () {
        //    return DB::table('users')->pluck('name', 'id');
        // });
        
        $client = DB::table('clients')->where('id',$client_id)->first();
        
        return view('pages.client.print.all',compact('get','currency','client'));
    }
}
