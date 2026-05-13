<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;

class oldBalanceArchiveController extends Controller
{
    
    public function load(Request $request){
        try {

            $get = DB::table('clients_transactions')->where('currency',$request->currency)->where('status','approved')
            ->where('calc','false')
            ->orderBy('id','desc')
            ->paginate(env('PAGEVIEW'));
            
            return view('pages.old_balance_archive.table',compact('get'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

}
