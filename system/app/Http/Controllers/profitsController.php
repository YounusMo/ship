<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;

class profitsController extends Controller
{
    
    public function load(Request $request){
        try {

            $from = $request->from;
            $to   = $request->to;
            
            return view('pages.profits.table',compact('from','to'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

}
