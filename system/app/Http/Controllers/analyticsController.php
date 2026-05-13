<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;

class analyticsController extends Controller
{
    private $analytics = __DIR__.'/analytics.json';

    public function load(Request $request){
        try {

            $analytics = json_decode($this->analytics);
            
            return view('pages.home.analytics',compact('analytics'));
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }
}
