<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\clientsController;
use App\Http\Controllers\branchesController;
use App\Http\Controllers\suppliersController;
use App\Http\Controllers\customsBrokersController;
use App\Http\Controllers\dataController;
use Illuminate\Support\Facades\Storage;

class seaController extends Controller
{
    /**
     * Columns the client form is allowed to set on store_sea via the
     * names[]/values[] JSON-array upload pattern. Any other column the
     * client tries to set is silently dropped — server-side fields like
     * created_by, created_date, created_time, images, canceled remain
     * authoritative.
     */
    private const STORE_SEA_ALLOWED_COLUMNS = [
        'transaction_number',
        'client_id',
        'client_code',
        'client_name',
        'company_name',
        'type',
        'number',
        'category',
        'kg',
        'cbm',
        'receipt',
        'brand',
        'notes',
        'ship_from',
    ];

    //-----------
    // Received
    //-----------
    public function load_received(Request $request){
        try {
            
            $get = DB::table('store_sea');

            if($request->search){
                
                $columns = Schema::getColumnListing('store_sea');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $get = $get->whereNull('canceled');

            $get = $get->where(function($q){
                $q->where('kg','>',0)->orWhere('cbm','>',0)->orWhere('number','>',0);
            });

            $get = $get->orderBy('id','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
                return DB::table('clients')
                    // ->where('deleted', 'false')
                    ->select('id', 'name', 'code','deleted')
                    ->get()
                    ->keyBy('id');
            });

            // Pre-compute which visible rows are locked so the table can
            // disable Edit + show a small lock badge without N+1 queries.
            $locked = $this->lockedShipmentIds('sea', $get->pluck('id')->all());

            return view('pages.shipping.sea.received.table',compact('get','count','clients','locked'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function new_received(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $transaction_number = null;

                $names  = json_decode($request->names);
                $values = json_decode($request->values);


                $data  = [];

                foreach ($names as $key => $value) {
                    if (in_array($value, self::STORE_SEA_ALLOWED_COLUMNS, true)) {
                        $data[$value] = $values[$key];
                    }
                }

                $data['created_date'] = date('Y-m-d');
                $data['created_time'] = date('H:i:s');
                $data['created_by']   = auth()->user()->id;

                $transaction_number = $data['transaction_number'] ?? null;
                $client_id          = $data['client_id'] ?? null;

                // Tenant boundary: branch_admins may only create receipts for
                // clients in their own branch. Admins pass straight through.
                // Also enforces existence — replaces the legacy $chk_client
                // truthiness check below (kept for now to preserve the err
                // path's exact response shape on edge cases).
                $this->assertCanAccessClient($client_id);

                $chk_client = DB::table('clients')->where('id',$client_id)->first();

                $files = [];

                $safeClientId = $this->safeIntSegment($client_id);
                if ($request->hasFile('images') && $safeClientId !== null) {
                    foreach ($request->file('images') as $file) {
                        $name = $this->storeUploadedImage($file, 'photos/sea/'.$safeClientId);
                        if ($name !== null) {
                            $files[] = $name;
                        }
                    }

                    $data['images'] = json_encode($files);
                }

                if($chk_client){

                    $newId = DB::table('store_sea')->insertGetId($data);

                    // Auto-generate per-piece tracking stickers (one row per
                    // physical piece) so the warehouse can print stickers
                    // immediately after receiving.
                    (new shipmentStickersController())->ensurePieces(
                        'store_sea',
                        (int) $newId,
                        max(1, (int) ($data['number'] ?? 1)),
                        (int) $client_id ?: null
                    );

                    // Mobile push: shipment received at warehouse. afterCommit
                    // ensures the client never sees "your shipment arrived"
                    // for a row that ultimately got rolled back.
                    $clientForNotify = \App\Models\Client::find($client_id);
                    if ($clientForNotify) {
                        $pieces = (int) ($data['number'] ?? 0);
                        $kg     = isset($data['kg']) ? (float) $data['kg'] : null;
                        $cbm    = isset($data['cbm']) ? (float) $data['cbm'] : null;
                        \Illuminate\Support\Facades\DB::afterCommit(function () use ($clientForNotify, $newId, $transaction_number, $pieces, $kg, $cbm) {
                            $clientForNotify->notify(new \App\Notifications\ShipmentStatusChanged(
                                mode: 'sea',
                                status: 'received',
                                sourceId: (int) $newId,
                                sourceTable: 'store_sea',
                                transactionNumber: $transaction_number,
                                pieces: $pieces ?: null,
                                kg: $kg,
                                cbm: $cbm,
                            ));
                        });
                    }

                    $err = false;
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


    public function edit_received(Request $request){
        try {

            $get = DB::table('store_sea')->where('id',$request->id)->first();

            if($get){
                if ($this->shipmentSourceIsLocked('sea', (int) $request->id)) {
                    // Source row has been delivered + paid. Returning 423
                    // (Locked) instead of rendering the edit form so the
                    // operator sees a clear reason and doesn't waste effort
                    // typing into a form that won't save anyway.
                    return response()->json([
                        'type'    => 'locked',
                        'message' => 'This shipment has been delivered and paid. Editing is no longer allowed.',
                    ], 423);
                }
                return view('pages.shipping.sea.received.edit',compact('get'));
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

    public function save_received(Request $request){
        try {
            // Hard stop: once the source row is delivered + paid, the
            // shipment's historical detail must stay frozen.
            if ($this->shipmentSourceIsLocked('sea', (int) $request->id)) {
                return response()->json([
                    'type'    => 'locked',
                    'message' => 'This shipment has been delivered and paid. Editing is no longer allowed.',
                ], 423);
            }

            $response = null;

            DB::transaction(function () use ($request, &$response) {
                $names  = json_decode($request->names);
                $values = json_decode($request->values);
                $deleted_ids = json_decode($request->deletedFiles);

                $data = [];
                foreach ($names as $key => $value) {
                    if (in_array($value, self::STORE_SEA_ALLOWED_COLUMNS, true)) {
                        $data[$value] = $values[$key];
                    }
                }

                // جلب الصور القديمة
                $get = DB::table('store_sea')->select(['images','client_id'])->where('id', $request->id)->first();
                $images = $get->images ? json_decode($get->images, true) : [];

                // حذف الصور المطلوبة — restrict to basename + only-if-tracked to block ../traversal
                if (!empty($deleted_ids)) {
                    $safeClientId = $this->safeIntSegment($get->client_id);
                    foreach ($deleted_ids as $id) {
                        $basename = basename((string) $id);
                        if ($safeClientId !== null && in_array($basename, $images, true)) {
                            $path = "photos/sea/{$safeClientId}/{$basename}";
                            if (file_exists($path)) {
                                unlink($path);
                            }
                        }
                        // $images stores basenames; filter by basename so the
                        // entry is actually removed even if the frontend sent a path.
                        $images = array_filter($images, fn($img) => $img !== $basename);
                    }
                }

                // إضافة الصور الجديدة
                $safeClientId = $this->safeIntSegment($get->client_id);
                if ($request->hasFile('images') && $safeClientId !== null) {
                    foreach ($request->file('images') as $file) {
                        $name = $this->storeUploadedImage($file, 'photos/sea/'.$safeClientId);
                        if ($name !== null) {
                            $images[] = $name;
                        }
                    }
                }

                if(count($images) > 0){
                    $data['images'] = json_encode(array_values($images));
                }else{
                    $data['images'] = null;
                }
               

                DB::table('store_sea')->where('id', $request->id)->update($data);

                // Re-sync pieces if `number` was edited. ensurePieces() will
                // add/cancel rows to match. Skip if `number` wasn't in the
                // submitted payload (then count is unchanged).
                if (array_key_exists('number', $data)) {
                    (new shipmentStickersController())->ensurePieces(
                        'store_sea',
                        (int) $request->id,
                        max(1, (int) $data['number']),
                        (int) ($get->client_id ?? 0) ?: null
                    );
                }

                $response = response()->json(['type' => 'success'], 200);
            });

            return $response;

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), ['exception' => $th]);
            return response()->json(['type' => 'error'], 500);
        }
    }

    //----------------------------------------------------------------------------------------
    // Inside
    //-----------

    public function load_inside(Request $request){
        try {
            
            $get = DB::table('store_sea');

            if($request->search){
                
                $columns = Schema::getColumnListing('store_sea');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $get = $get->whereNull('canceled');
            
            $get = $get->where(function($q){
                $q->where('kg','>',0)->orWhere('cbm','>',0)->orWhere('number','>',0);
            });

            $get = $get->orderBy('id','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
                return DB::table('clients')
                   // ->where('deleted', 'false')
                    ->select('id', 'name', 'code','deleted')
                    ->get()
                    ->keyBy('id');
            });
            

            return view('pages.shipping.sea.inside.table',compact('get','count','clients'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function cancel(Request $request){
        try {
            DB::transaction(function () use ($request) {
                DB::table('store_sea')->where('id',$request->id)->update([
                    'canceled'      => 'true',
                    'canceled_by'   => auth()->user()->id,
                    'canceled_date' => date('Y-m-d'),
                    'canceled_time' => date('H:i:s'),
                ]);

                // Cancelling the source row invalidates every printed sticker
                // for it — flip the pieces so /track/{code} reports cancelled.
                // Same transaction: if pieces fail, the canceled flag rolls back
                // rather than leaving the two out of sync.
                (new shipmentStickersController())->cancelPiecesFor('store_sea', (int) $request->id);
            });

            return response()->json(['type' => 'success'], 200);
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function get_eject_modal(Request $request){
        try {

            $get = DB::table('store_sea')->where('id',$request->id)->first();

            if($get){
                return view('pages.shipping.sea.inside.eject',compact('get'));
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


    public function eject(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $dataController = new dataController();
                
                $transaction_number = $request->transaction_number;
                $id       = $request->id;
                $number   = $request->number;
                $cbm      = $request->cbm;
                $kg       = $request->kg;
                $unit     = $request->unit;
                $currency = $request->currency;
                $price    = $request->price;
                $plus     = $request->plus ? $request->plus : 0;

                $err  = false;
                $stop = false;
                $stopMsg = null;
                
                if($id && strlen($cbm) > 0 && strlen($kg) > 0 && strlen($number) > 0 && $unit && $currency && $price){ 
                    $get = DB::table('store_sea')->where('id',$id)->first();
                    if($get){
                        
                        // if(floatval($kg) > floatval($get->kg)){
                        //     $stopMsg = response()->json(['err' => 'kg'],200);
                        //     $stop = true;
                        // }
                        
                        // if(floatval($cbm) > floatval($get->cbm)){
                        //     $stopMsg = response()->json(['err' => 'cbm'],200);
                        //     $stop = true;
                        // }
                        
                        // if(floatval($number) > floatval($get->number)){
                        //     $stopMsg = response()->json(['err' => 'number'],200);
                        //     $stop = true;
                        // }
                        $currency_exchange_rates = $dataController->currency_exchange_rates;

                        $exchange_rate = null;
                        if($currency !== 'usd'){
                            $exchange_rate = floatval($currency_exchange_rates[$currency]);
                        }
                        

                        if(!$stop){

                            $number_ = floatval($get->number) - floatval($number);
                            $cbm_    = floatval($get->cbm)    - floatval($cbm);
                            $kg_     = floatval($get->kg)     - floatval($kg);

                            $chk_exist = DB::table('store_out_sea')->where('in_id',$id)->first();

                            $data = [
                                'canceled'     => 'false',
                                'number'       => $number,
                                'cbm'          => $cbm,
                                'kg'           => $kg,
                                'price'        => $price,
                                'exchange_rate'=> $exchange_rate,
                                'plus'         => $plus,
                                'unit'         => $unit,
                                'currency'     => $currency,
                                'in_id'        => $id,
                                'client_id'    => $get->client_id,
                                'client_code'  => $get->client_code,
                            ];

                            $data['created_date'] = date('Y-m-d');
                            $data['created_time'] = date('H:i:s');
                            $data['created_by']   = auth()->user()->id;

                            $out_id = DB::table('store_out_sea')->insertGetId($data);

                            DB::table('store_sea')->where('id',$request->id)->update([
                                'out_id' => $out_id,
                                'number' => $number_,
                                'cbm'    => $cbm_,
                                'kg'     => $kg_,
                            ]);
                        }
                    }     
                }else{
                    $err = true;
                }

                if($err){
                    $response = response()->json(['type' => 'error'],500);
                }else{
                    if($stop){
                        $response = $stopMsg;
                    }else{
                        $response = response()->json(['type' => 'success'],200);
                    }
                    
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

    //----------------------------------------------------------------------------------------
    // Outside
    //-----------

    public function load_outside(Request $request){
        try {
            
            $get = DB::table('store_out_sea');

            if($request->search){
                
                $columns = Schema::getColumnListing('store_out_sea');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $get = $get->whereNull('container_id');
            $get = $get->orderBy('id','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
                return DB::table('clients')
                  // ->where('deleted', 'false')
                    ->select('id', 'name', 'code','deleted')
                    ->get()
                    ->keyBy('id');
            });
            

            return view('pages.shipping.sea.outside.table',compact('get','count','clients'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }


    public function create_container(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                
                $ids     = json_decode($request->ids);
                $name    = trim($request->name);
                $number  = trim($request->number);
                $arrival = trim($request->arrival);
                // $type    = trim($request->type);
                $size    = trim($request->size);
                $supplier= trim($request->supplier);
                $notes= trim($request->notes);

                $err  = false;

                if($ids && $number && $name && $arrival  && $size && $supplier){ 
                    $dataController = new dataController();
                    $sea_purpose = $dataController->sea_purpose;

                    $content_of_fees =  ['currency' => [] , 'value' => [] , 'notes' => [] , 'branch' => [] , 'exchange_rate' => [] , 'result_usd' => []];
                    $fees = [];

                    foreach ($sea_purpose as $key => $value) {
                        $fees[$key] = $content_of_fees;
                    }

                    $container_id = DB::table('containers_sea')->insertGetId([
                        'type'           => 'full',
                        // 'packaging_type' => $type,
                        'number'         => $number,
                        'supplier'       => $supplier,
                        'name'           => $name,
                        'notes'          => $notes,
                        'fees'           => json_encode($fees),
                        'arrival'        => $arrival,
                        'status'         => 'processing',
                        'custom_status'  => 'approved',
                        'canceled'       => 'false',
                        'size'           => $size,
                        'created_date'   => date('Y-m-d'),
                        'created_time'   => date('H:i:s'),
                        'created_by'     => auth()->user()->id,
                    ]);

                    DB::table('store_out_sea')->whereIn('id',$ids)->update([
                        'container_id' => $container_id,
                        'new_price'    => 0,
                    ]);

                    Cache::forget('containers_sea');
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

    public function insert_exist(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                
                $ids     = explode(',', $request->ids);

                $container_id = $request->container_id;

                for($i = 0 ; $i < count($ids) ; $i++){
                    DB::table('store_out_sea')->where('id',$ids[$i])->update([
                        'container_id' => $container_id,
                        'new_price'    => 0,
                    ]);
                }

                Cache::forget('containers_sea');
                $response = response()->json(['type' => 'success'],200);
            });

            return $response;

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    //----------------------------------------------------------------------------------------
    // Containers
    //-----------

    public function load_containers(Request $request){
        try {
            
            $get = DB::table('containers_sea');

            if($request->search){
                
                $columns = Schema::getColumnListing('containers_sea');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $get = $get->where('canceled','false');
            $get = $get->orderBy('id','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            return view('pages.shipping.sea.containers.table',compact('get','count'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    function add_link(Request $request){
        try {
            
            DB::table('containers_sea')->where('id',$request->id)->update([
                'link' => $request->link
            ]);

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function cancel_in_container(Request $request){ // إلغاء شحنة داخل الحاوية

        try {
            $branchesController   = new branchesController();
            $clientsController   = new clientsController();

            $all = DB::table('store_out_sea')->where('id',$request->id)->get();

        
            if(!$all){
                return;
            }

            foreach ($all as $key => $x) {
                $container_id = $x->container_id;
                $client = DB::table('clients_transactions')->whereIn('type',['exp_withdraw','exp_deposit'])->where('client_id',$x->client_id)->get();
            
                foreach ($client as $key => $value) {
                    $data = json_decode($value->data,true);

                    if($data['sea_sky'] === 'sea' && $data['container_id'] == $container_id){
                        DB::table('clients_transactions')->where('id',$value->id)->delete();
                        DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                        $clientsController->update_balance($x->client_id);
                    }
                }

                $br = DB::table('branches_transactions')->whereIn('type',['exp_withdraw','exp_deposit'])->get();
                
                foreach ($br as $key => $value) {
                    $data = json_decode($value->data,true);

                    if($data['sea_sky'] === 'sea' && $data['container_id'] == $container_id){
                        DB::table('branches_transactions')->where('id',$value->id)->delete();
                        DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                    }
                }
            }

            DB::table('store_out_sea')->where('id',$request->id)->update([
                'canceled' => 'true',
                'kg'    => 0,
                'price' => 0,
                'plus'  => 0,
                'payment'  => null,
                'payment_pending'  => null,
                'branch'  => null,
            ]);

            $branches = DB::table('branches')->get();

            foreach ($branches as $key => $branch) {
                $branchesController->update_balance($branch->id);
            }

        } catch (\Throwable $th) {
             Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function cancel_container(Request $request){ // إلغاء الحاوية كاملة

       try {
            // $allow_cancel = true;

            // يتم التأكد من عدم دفع أي مبالغ نقدية
            // $chk_money_1 = DB::table('store_out_sea')->where('container_id',$request->id)->whereNotNull('payment')->count();
            // $chk_money_2 = DB::table('containers_sea_fees')->where('container_id',$request->id)->count();
            // $chk_money_3 = DB::table('suppliers_transactions')->where('sky_sea','sea')->where('container_id',$request->id)->count();
            // $chk_money_4 = DB::table('customs_brokers_transactions')->where('sky_sea','sea')->where('container_id',$request->id)->count();

            // if($chk_money_1 > 0 ||$chk_money_2 > 0 ||$chk_money_3 > 0 ||$chk_money_4 > 0 ){
            //     $allow_cancel = false;
            // }

            $suppliersController  = new suppliersController();
            $branchesController   = new branchesController();
            $customsBrokersController   = new customsBrokersController();
            $clientsController   = new clientsController();

            
            $get = DB::table('containers_sea')->where('id',$request->id)->first();

            // if($allow_cancel){
                DB::table('containers_sea')->where('id',$request->id)->update([
                    'canceled'    => 'true',
                    'canceled_by' => auth()->user()->id,
                    'canceled_date' => date('Y-m-d'),
                    'canceled_time' => date('H:i:s'),
                ]);
            // }

            if($get->type === 'custom'){
                $client = DB::table('clients_transactions')->whereIn('type',['exp_custom_withdraw','exp_custom_deposit'])->where('client_id',$get->client_id)->get();
                
                foreach ($client as $key => $value) {
                    $data = json_decode($value->data,true);

                    if($data['sea_sky'] === 'sea' && $data['container_id'] == $get->id){
                        DB::table('clients_transactions')->where('id',$value->id)->delete();
                        DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                        $clientsController->update_balance($get->client_id);
                    }
                }

                $br = DB::table('branches_transactions')->whereIn('type',['exp_custom_withdraw','exp_custom_deposit'])->get();
                
                foreach ($br as $key => $value) {
                    $data = json_decode($value->data,true);

                    if($data['sea_sky'] === 'sea' && $data['container_id'] == $get->id){
                        DB::table('branches_transactions')->where('id',$value->id)->delete();
                        DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                    }
                }
            }

            if($get->type === 'full'){
                $all = DB::table('store_out_sea')->where('container_id',$get->id)->get();

                foreach ($all as $key => $x) {
                    $client = DB::table('clients_transactions')->whereIn('type',['exp_withdraw','exp_deposit'])->where('client_id',$x->client_id)->get();
                
                    foreach ($client as $key => $value) {
                        $data = json_decode($value->data,true);

                        if($data['sea_sky'] === 'sea' && $data['container_id'] == $get->id){
                            DB::table('clients_transactions')->where('id',$value->id)->delete();
                            DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                            $clientsController->update_balance($x->client_id);
                        }
                    }

                    $br = DB::table('branches_transactions')->whereIn('type',['exp_withdraw','exp_deposit'])->get();
                    
                    foreach ($br as $key => $value) {
                        $data = json_decode($value->data,true);

                        if($data['sea_sky'] === 'sea' && $data['container_id'] == $get->id){
                            DB::table('branches_transactions')->where('id',$value->id)->delete();
                            DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                        }
                    }
                }
                
            }
            
            $supp = DB::table('suppliers_transactions')->where('container_number',$get->number)->where('sky_sea','sea')->get();

            foreach ($supp as $key => $value) {
                $supplier_id = $value->supplier_id;
                DB::table('suppliers_transactions')->where('id',$value->id)->delete();
                DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                $suppliersController->update_balance($supplier_id);
            }

            $brok = DB::table('customs_brokers_transactions')->where('container_id',$get->id)->where('sky_sea','sea')->get();
            
            foreach ($brok as $key => $value) {
                $broker_id = $value->broker_id;
                DB::table('customs_brokers_transactions')->where('id',$value->id)->delete();
                DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->where('auto_id',$value->auto_id)->delete();
                $customsBrokersController->update_balance($broker_id);
            }


            DB::table('containers_sea_fees')->where('container_id',$get->id)->delete();

            $branches = DB::table('branches')->get();

            foreach ($branches as $key => $branch) {
                $branchesController->update_balance($branch->id);
            }
       } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
       }
    }

    public function load_canceled_containers(Request $request){
        try {
            
            $get = DB::table('containers_sea');

            if($request->skyrch){
                
                $columns = Schema::getColumnListing('containers_sea');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $skyrch = $this->escapeLike($request->skyrch);

                $get = $get->where(function($q) use ($columns_, $skyrch) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$skyrch}%");
                    }
                });
            }
            
            $get = $get->where('canceled','true');
            $get = $get->orderBy('canceled_date','DESC');
            $get = $get->orderBy('canceled_time','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            return view('pages.shipping.sea.canceled_containers.table',compact('get','count'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }


    public function withdraw_custom_broker(Request $request){
        try {
            
            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $get = DB::table('containers_sea')->where('id',$request->id)->first();
                $dataController       = new dataController();
                $currency_exchange_rates = $dataController->currency_exchange_rates;

                if($get){
                    $payment = $request->payment;

                    // if ($currency !== 'usd' && isset($currency_exchange_rates[$currency])) {
                        $branch  = $payment === 'pay2' ?  $request->branch : null;

                        if($payment === 'pay2'){
                            $branchesController = new branchesController();
                            $calc = $branchesController->allow_complete_blance($branch,$request->value,$request->currency);
                            if(! $calc){
                                $response = response()->json(['type' => 'balance_err'],200);
                                return;
                            }
                        }
                        
                        $widthd_data = [
                            'type'        => 'custom_container_withdraw',
                            'value'       => $request->value,
                            'currency'    => $request->currency,
                            'broker_id'   => $request->custom,
                            'notes'       => $request->notes,
                            'pay_for'     => $request->pay_for,
                            'plus_minus'  => 'minus',
                            'branch'      => $branch,
                            'container_number'=> $get->number,
                            'container_id'=> $get->id,
                            'sky_sea'=> 'sea',
                            'transaction_number' => 'custom_container_withdraw_'.date('Ymd').$request->id,
                        ];
                        
                        $reqquest = new Request($widthd_data);
                        
                        $customsBrokersController = new customsBrokersController();

                        $customsBrokersController->withdraw($reqquest);

                        if($payment === 'pay2'){
                            
                            $widthd_data = [
                                'type'        => 'custom_container_deposit',
                                'value'       => $request->value,
                                'currency'    => $request->currency,
                                'broker_id'   => $request->custom,
                                'notes'       => $request->notes,
                                'pay_for'     => $request->pay_for,
                                'plus_minus'  => 'plus',
                                'container_id'=> $get->id,
                                'container_number'=> $get->number,
                                'branch'      => $branch,
                                'sky_sea'=> 'sea',
                                'transaction_number' => 'custom_container_deposit_'.date('Ymd').$request->id,
                            ];
                            
                            $reqquest = new Request($widthd_data);
                            
                            $customsBrokersController = new customsBrokersController();

                            $response = $customsBrokersController->deposit($reqquest);

                        }

                        $purpose      = $request->pay_for;
                        $fees = json_decode($get->fees, true);
                        $fees[$purpose]['notes'][]    = $request->notes;
                        $fees[$purpose]['value'][]    = $request->value;
                        $fees[$purpose]['branch'][]   = $branch;
                        $fees[$purpose]['currency'][] = $request->currency;

                        if($request->currency !== 'usd'){
                            $rate = floatval($currency_exchange_rates[$request->currency]);
                            $usd_value = floatval($request->value) / $rate;
                            $supplier_usd_value = $usd_value;
                            $fees[$purpose]['result_usd'][]    = number_format($usd_value, 2, '.', '');
                            $fees[$purpose]['exchange_rate'][] = $rate;
                            
                        }else{
                            $fees[$purpose]['result_usd'][]    = number_format(floatval($request->value), 2, '.', '');
                            $fees[$purpose]['exchange_rate'][] = 0;
                        }
                        

                        $containers_sea_fees = [
                            'container_id'  => $get->id,
                            'purpose'       => $purpose,
                            'container_number'  => $get->number,
                            'result_usd'    => $request->value,
                            'exchange_rate' => 0,
                            'value'         => $request->value,
                            'branch'        => $branch,
                            'currency'      => $request->currency,
                            'notes'         => $request->notes,
                            'type'          => 'container_sea_withdraw',
                            'created_date'  => date('Y-m-d'),
                            'created_time'  => date('H:i:s'),
                            'created_by'    => auth()->user()->id,
                        ];
                    // }else{

                    // }
                    DB::table('containers_sea_fees')->insert($containers_sea_fees);

                    DB::table('containers_sea')->where('id', $get->id)->update([
                        'fees' => json_encode($fees)
                    ]);

                    // $err  = false;

                    // if($err){
                    //     $response = response()->json(['type' => 'error'],500);
                    // }else{
                    //     $response = response()->json(['type' => 'success',],200);
                    // }
                }
            });
            
            return $response;

        }catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    public function change_status_custom_container(Request $request){
        try {
            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $dataController = new dataController();
                $clientsController = new clientsController();
                $suppliersController  = new suppliersController();
                $branchesController  = new branchesController();
                $sea_purpose = $dataController->sea_purpose;
                
                if((! $request->id || ! $request->status) || ! in_array($request->status, ['approved','pending','rejected'])){
                    return;
                }
                $get = DB::table('containers_sea')->where('id',$request->id)->first();

                DB::table('containers_sea')->where('id',$request->id)->update([
                    'custom_status' => $request->status,
                ]);

                if($request->status !== 'approved'){
                    return;
                }   

                $container_data = [
                    'container_name'   => $get->name,
                    'container_number' => $get->number,
                    'container_id'     => $get->id,
                    'sea_sky'          => 'sea',
                ];

                $this->client_withd($get->client_id,$get->client_price,$get->cost,$get->commission,$container_data,$get->pay_branch_client);

                if($get->custom_container_payment === 'pay2'){
                    $this->client_deposit($get->client_id,$get->client_price,$get->cost,$get->commission,$container_data,$get->pay_branch_client);
                }

                $widthd_data = [
                    'type'        => 'custom_container_withdraw',
                    'value'       => $get->cost,
                    'currency'    => 'usd',
                    'supplier_id' => $get->supplier,
                    'plus_minus'  => 'minus',
                    'container_number'=> $get->number,
                    'container_id'=> $get->id,
                    'sky_sea'=> 'sea',
                    'transaction_number' => 'custom_container_withdraw_'.date('Ymd').$get->id,
                ];
                
                $reqquest = new Request($widthd_data);

                $suppliersController->withdraw($reqquest);

                if($get->payment_supplier === 'pay2'){
                    $widthd_data = [
                        'type'        => 'custom_container_deposit',
                        'value'       => $get->cost,
                        'currency'    => 'usd',
                        'supplier_id' => $get->supplier,
                        'plus_minus'  => 'plus',
                        'container_number'=> $get->number,
                        'container_id'=> $get->id,
                        'branch'=> $get->pay_branch_supplier,
                        'sky_sea'=> 'sea',
                        'transaction_number' => 'custom_container_deposit_'.date('Ymd').$get->id,
                    ];
                    
                    $reqquest = new Request($widthd_data);

                    $x = $suppliersController->deposit($reqquest);
                    if($x === 'balance_err'){
                        return response()->json(['type' => 'balance_err_supplier'],200);
                    }
                }
                
            });
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }
    public function new_custom_container(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                
                $name    = trim($request->name);
                $number  = trim($request->number);
                $arrival = trim($request->arrival);
                $size    = trim($request->size);
                $payment = trim($request->payment);
                $branch  = trim($request->branch);
                $branch2  = trim($request->branch2);
                $notes  = trim($request->notes);
                $client_id    = trim($request->client_id);
                $supplier     = trim($request->supplier);
                $ship_from    = trim($request->ship_from);
                $payment_supplier    = trim($request->payment_supplier);
                $cost         = floatval($request->cost);
                $commission   = floatval($request->commission);
                $client_price = floatval($request->client_price);
                $profit       = ($client_price + $commission) - $cost ;

                $err  = false;

                if($number && $name && $arrival && $size && $ship_from && $client_id && $supplier && $payment_supplier){ 
                    
                    $dataController = new dataController();
                    $clientsController = new clientsController();
                    $suppliersController  = new suppliersController();
                    $branchesController  = new branchesController();
                    $sea_purpose = $dataController->sea_purpose;

                    // if($payment === 'pay2'){
                    //     $calc = $branchesController->allow_complete_blance($branch,($client_price + $commission),'usd');
                    //     if(! $calc){
                    //         $response = response()->json(['type' => 'balance_err'],200);
                    //         return;
                    //     }
                    // }

                    // if($payment_supplier === 'pay2'){
                    //     $calc = $branchesController->allow_complete_blance($branch,$cost,'usd');
                    //     if(! $calc){
                    //         $response = response()->json(['type' => 'balance_err'],200);
                    //         return;
                    //     }
                    // }
                    

                    $content_of_fees =  ['currency' => [] , 'value' => [] , 'notes' => [] , 'branch' => [] , 'exchange_rate' => [] , 'result_usd' => []];
                    $fees = [];

                    foreach ($sea_purpose as $key => $value) {
                        $fees[$key] = $content_of_fees;
                    }

                    $container_id = DB::table('containers_sea')->insertGetId([
                        'type'                      => 'custom',
                        'client_id'                 => $client_id,
                        'supplier'                  => $supplier,
                        'pay_branch_client'         => $branch,
                        'pay_branch_supplier'       => $branch2,
                        'ship_from'                 => $ship_from,
                        'profit'                    => $profit,
                        'client_price'              => $client_price,
                        'custom_container_payment'  => $payment,
                        'payment_supplier'          => $payment_supplier,
                        'commission'                => $commission,
                        'notes'                     => $notes,
                        'cost'                      => $cost,
                        'number'                    => $number,
                        'name'                      => $name,
                        'fees'                      => json_encode($fees),
                        'arrival'                   => $arrival,
                        'status'                    => 'processing',
                        'custom_status'             => 'pending',
                        'canceled'                  => 'false',
                        'size'                      => $size,
                        'created_date'              => date('Y-m-d'),
                        'created_time'              => date('H:i:s'),
                        'created_by'                => auth()->user()->id,
                    ]);

                    
                    Cache::forget('containers_sea');
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

    public function client_withd($client_id,$client_price,$cost,$commission,$container_data,$branch){
        $dataController = new dataController();
        $clientsController = new clientsController();
        $branchesController = new branchesController();
        $treasuryController = new treasuryController();
        
        $last_auto_id = $dataController->tr_code;
                    
        $last_auto_id_ = DB::table('clients_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
        $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;
        
        $remaining_balance = $clientsController->calc_balance($client_id,'usd',true);

        $transaction_number = 'exp_custom_withdraw'.date('Ymd').$client_id;

        $container_data = json_encode($container_data);

        DB::table('clients_transactions')->insert([
            'transaction_number' =>  $transaction_number,
            'value'        => $client_price + $commission,
            'currency'     => 'usd',
            'auto_id'      => $auto_id,
            'commission'   => 0,
            'status'        => 'approved',
            'remaining_balance' => $remaining_balance - ($client_price + $commission),
            'type'         => 'exp_custom_withdraw',
            'data'         => $container_data,
            'plus_minus'   => 'minus',
            'branch'       => null,
            'client_id'    => $client_id,
            'created_by'   => auth()->user()->id,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
        ]);

        $clientsController->update_balance($client_id);

        if($branch){
            DB::table('branches_transactions')->insert([
                'transaction_number' => $transaction_number,
                'value'        => $cost,
                'currency'     => 'usd',
                'auto_id'      => $auto_id,
                'type'         => 'exp_custom_withdraw',
                'data'         => $container_data,
                'plus_minus'   => 'minus',
                'branch'       => $branch,
                'notes'        => null,
                'created_by'   => auth()->user()->id,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
            ]);

            $branchesController->update_balance($branch);

            // $treasuryController->insert($transaction_number,'exp_custom_withdraw','minus',$auto_id,$container_data,($client_price + $commission),'usd',0,$branch,null,$client_id);
        }
    }

    public function client_deposit($client_id,$client_price,$cost,$commission,$container_data,$branch){
        $treasuryController  = new treasuryController();
        $dataController      = new dataController();
        $clientsController   = new clientsController();
        $branchesController  = new branchesController();
        
        $last_auto_id = $dataController->tr_code;
                    
        $last_auto_id_ = DB::table('clients_transactions')->select('auto_id')->orderBy('auto_id','DESC')->limit(1)->first();
        $auto_id = $last_auto_id_ ? $last_auto_id_->auto_id +1 : $last_auto_id;
        
        $remaining_balance = $clientsController->calc_balance($client_id,'usd',true);

        $transaction_number = 'exp_custom_deposit'.date('Ymd').$client_id;

        $container_data = json_encode($container_data);

        DB::table('clients_transactions')->insert([
            'transaction_number' => $transaction_number,
            'value'        => $client_price + $commission,
            'currency'     => 'usd',
            'auto_id'      => $auto_id,
            'commission'   => 0,
            'status'        => 'approved',
            'remaining_balance' => $remaining_balance + ($client_price + $commission),
            'type'         => 'exp_custom_deposit',
            'data'         => $container_data,
            'plus_minus'   => 'plus',
            'branch'       => $branch,
            'client_id'    => $client_id,
            'created_by'   => auth()->user()->id,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
        ]);

        DB::table('branches_transactions')->insert([
            'transaction_number' => $transaction_number,
            'value'        => $client_price + $commission,
            'currency'     => 'usd',
            'auto_id'      => $auto_id,
            'type'         => 'exp_custom_deposit',
            'data'         => $container_data,
            'plus_minus'   => 'plus',
            'branch'       => $branch,
            'notes'        => null,
            'created_by'   => auth()->user()->id,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
        ]);


        $clientsController->update_balance($client_id);
        $branchesController->update_balance($branch);

        $treasuryController->insert($transaction_number,'exp_custom_deposit','plus',$auto_id,$container_data,($client_price + $commission),'usd',0,$branch,null,$client_id);
    }

    public function show_custom_container(Request $request){
        $get = DB::table('containers_sea')->where('id',$request->id)->first();

        if($get){
            return view('pages.shipping.sea.containers.show_custom_container',compact('get'));
        }else{
            return response()->json('err',500);
        }
    }

    public function print_container(Request $request){
        $id = $request->id;
        return view('pages.shipping.sea.containers.print_container',compact('id'));
    }

    public function save_container(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $clientsController = new clientsController();
                $dataController = new dataController();

                $data  = json_decode($request->data);


                $currency_exchange_rates = $dataController->currency_exchange_rates;
                
                $err  = false;

                if($data){ 
                    foreach ($data as $key => $value) {
                        $get = DB::table('store_out_sea')->where('id',$value->id)->first();
                        if($value->payment && $get->payment === null || $value->payment_pending === 'confirmed' && $get->payment_pending !== 'confirmed'){
                            
                            if($get->payment_pending !== 'confirmed' || !$get->payment_pending){
                                
                                $total = 0;

                                if(floatval($get->new_price) > 0){
                                    $total = $get->new_price;
                                }else{
                                    if($get->unit === 'kg'){
                                        $total = number_format(floatval($value->price) * floatval($value->kg), 2, '.', '');
                                    }

                                    if($get->unit === 'cbm'){
                                        $total = number_format(floatval($value->price) * floatval($value->cbm), 2, '.', '');
                                    }

                                    if(floatval($value->plus) > 0){
                                        $total += floatval($value->plus);
                                    }
                                }

                                $get_notes = DB::table('store_sea')->where('id',$get->in_id)->first();

                                $get_container = DB::table('containers_sea')
                                    ->select(['name','number','id'])
                                    ->where('id',$request->container_id)
                                ->first();

                                if($value->payment_pending === 'confirmed' && $get->payment_pending !== 'confirmed'){
                                    $container_data = [
                                        'container_name'   => $get_container->name,
                                        'container_number' => $get_container->number,
                                        'container_id'     => $get_container->id,
                                        'sea_sky'          => 'sea',
                                    ];

                                    $withd_data = [
                                        'type'       => 'exp_withdraw',
                                        'commission' => 0,
                                        'id'         => $get->client_id,
                                        'value'      => $total,
                                        'notes'      => $get_notes->notes,
                                        'data'       => $container_data,
                                        'branch'     => $value->branch ?? null,
                                        'currency'   => $value->currency,
                                        'treasury'   => false,
                                        'transaction_number' => 'exp_withdraw_'.date('Ymd').$get->client_id,
                                    ];

                                    $reqquest = new Request($withd_data);

                                    $clientsController->withdraw($reqquest);

                                    if($value->payment === 'pay2'){
                                        $deposit_data = [
                                            'type'       => 'exp_deposit',
                                            'commission' => 0,
                                            'id'         => $get->client_id,
                                            'value'      => $total,
                                            'notes'      => $get_notes->notes,
                                            'data'       => $container_data,
                                            'branch'     => $value->branch,
                                            'currency'   => $value->currency,
                                            'treasury'   => false,
                                            'transaction_number' => 'exp_deposit_'.date('Ymd').$get->client_id,
                                        ];

                                        $reqquest = new Request($deposit_data);

                                        $clientsController->deposit($reqquest);
                                    }

                                    $exchange_rate = null;
                                    if($value->currency !== 'usd'){
                                        $exchange_rate = floatval($currency_exchange_rates[$value->currency]);
                                    }
                                    
                                    DB::table('store_out_sea')->where('id',$value->id)->update([
                                        'exchange_rate' => $exchange_rate
                                    ]);
                                } 
                            }

                            $payment_pending = null;

                            if(strlen($get->payment_pending) < 1 && $value->payment){
                                $payment_pending = 'pending';
                            }
                            
                            if($value->payment_pending === 'confirmed' && $get->payment_pending !== 'confirmed'){
                                $payment_pending = 'confirmed';
                            }

                            DB::table('store_out_sea')->where('id',$value->id)->where(function($q){
                               $q->where('payment_pending','pending')->orWhereNull('payment_pending');
                            })->update([
                                'number'   => $value->number,
                                'cbm'      => $value->cbm,
                                'kg'       => $value->kg,
                                'price'    => $value->price,
                                'plus'     => $value->plus,
                                'currency' => $value->currency,
                                'branch'   => $value->branch,
                                'new_price'=> $value->new_price,
                                'payment_pending'=> $payment_pending,
                                'payment'  => $value->payment ? $value->payment  : null,
                            ]);
                        }
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

    public function change_status(Request $request){
        if(!in_array(auth()->user()->type, ['admin','branch_admin'])){
            return;
        }
        DB::table('containers_sea')->where('id',$request->id)->update([
            'status' => $request->val
        ]);
    }

    public function sea_withdraw(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {

                $dataController       = new dataController();
                $suppliersController  = new suppliersController();
                $branchesController   = new branchesController();
                $currency_exchange_rates = $dataController->currency_exchange_rates;

                $value        = floatval($request->value);
                $currency     = $request->currency;
                $branch       = $request->branch;
                $purpose      = $request->purpose;
                $container_id = $request->container;
                $payment_supplier = $request->payment_supplier;
                $notes        = $request->notes;

                $err = false;

                if ($value && $currency && $branch && $purpose && $container_id) {
                    $container = DB::table('containers_sea')->where('id', $container_id)->first();

                    if ($container) {

                        $calc = $branchesController->allow_complete_blance($branch,$value,$currency);
                        if(! $calc){
                            $response = response()->json(['type' => 'balance_err'],200);
                            return;
                        }
                        
                        $fees = json_decode($container->fees, true);

                        $fees[$purpose]['notes'][]    = $notes;
                        $fees[$purpose]['value'][]    = $value;
                        $fees[$purpose]['currency'][] = $currency;
                        $fees[$purpose]['branch'][]   = $branch;

                        $usd_value = $value;

                        $containers_sea_fees = [];

                        $supplier_usd_value = 0;

                        if ($currency !== 'usd' && isset($currency_exchange_rates[$currency])) {
                            
                            $rate = floatval($currency_exchange_rates[$currency]);
                            $usd_value = floatval($value) / $rate;
                            $supplier_usd_value = $usd_value;
                            $fees[$purpose]['result_usd'][]    = number_format($usd_value, 2, '.', '');
                            $fees[$purpose]['exchange_rate'][] = $rate;

                            $containers_sea_fees = [
                                'container_id'  => $container_id,
                                'purpose'       => $purpose,
                                'container_number'  => $container->number,
                                'result_usd'    => number_format($usd_value, 2, '.', ''),
                                'exchange_rate' => $rate,
                                'value'         => $value,
                                'branch'        => $branch,
                                'currency'      => $currency,
                                'notes'         => $notes,
                                'type'          => 'container_sea_withdraw',
                                'created_date'  => date('Y-m-d'),
                                'created_time'  => date('H:i:s'),
                                'created_by'    => auth()->user()->id,
                            ];
                            
                        } else {
                            $fees[$purpose]['result_usd'][]    = number_format($value, 2, '.', '');
                            $fees[$purpose]['exchange_rate'][] = 0;

                            $supplier_usd_value = $value;

                            $containers_sea_fees = [
                                'container_id'  => $container_id,
                                'purpose'       => $purpose,
                                'container_number'  => $container->number,
                                'result_usd'    => $value,
                                'exchange_rate' => 0,
                                'value'         => $value,
                                'branch'        => $branch,
                                'currency'      => 'usd',
                                'notes'         => $notes,
                                'type'          => 'container_sea_withdraw',
                                'created_date'  => date('Y-m-d'),
                                'created_time'  => date('H:i:s'),
                                'created_by'    => auth()->user()->id,
                            ];
                        }
                        
                        DB::table('containers_sea_fees')->insert($containers_sea_fees);

                        DB::table('containers_sea')->where('id', $container_id)->update([
                            'fees' => json_encode($fees)
                        ]);

                        if($purpose === 'container_fee_value'){
                            $widthd_data = [
                                'type'        => 'container_withdraw',
                                'value'       => $value,
                                'currency'    => $currency,
                                'supplier_id' => $container->supplier,
                                'plus_minus'  => 'minus',
                                'notes'       => $notes,
                                'container_number'  => $container->number,
                                'container_id'=> $container->id,
                                'sky_sea'=> 'sea',
                                'transaction_number' => 'container_withdraw_'.date('Ymd').$container->id,
                            ];
                            
                            $reqquest = new Request($widthd_data);

                            $suppliersController->withdraw($reqquest);

                            if($payment_supplier === 'pay2'){
                                $widthd_data = [
                                    'type'        => 'container_deposit',
                                    'value'       => $value,
                                    'currency'    => $currency,
                                    'supplier_id' => $container->supplier,
                                    'plus_minus'  => 'plus',
                                    'notes'       => $notes,
                                    'container_number'=> $container->number,
                                    'container_id'=> $container->id,
                                    'branch'=> $branch,
                                    'sky_sea'=> 'sea',
                                    'transaction_number' => 'container_deposit_'.date('Ymd').$container->id,
                                ];
                                
                                $reqquest = new Request($widthd_data);

                                $suppliersController->deposit($reqquest);
                            }
                        }else{
                            //Withdraw from branch
                            $branchesController = new branchesController();
                            $branch_withd_data = [
                                'sky_sea' => 'sea',
                                'container_number' => $container->number,
                                'container_id'     => $container->id,
                            ];

                            $widthd_data = [
                                'type'       => 'container_withdraw',
                                'commission' => 0,
                                'value'      => $value,
                                'data'       => json_encode($branch_withd_data) ,
                                'branch'     => $branch,
                                'currency'   => $currency,
                                'transaction_number' => 'container_withdraw_'.date('Ymd').$container->id,
                            ];
                            
                            $reqquest = new Request($widthd_data);

                            $branchesController->add_expenses($reqquest);
                        }

                    } else {
                        $err = true;
                    }
                } else {
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

    //-----------
    // Canceled
    //-----------
    public function load_canceled(Request $request){
        try {
            
            $get = DB::table('store_sea');

            if($request->search){
                
                $columns = Schema::getColumnListing('store_sea');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $get = $get->where('canceled','true');

            $get = $get->orderBy('id','DESC');

            $count = $get->count();
            $get   = $get->paginate(env('PAGEVIEW'));

            $clients = Cache::remember('clients_compant_accounting', env("CACHE"), function () {
                return DB::table('clients')
                    ->where('deleted', 'false')
                    ->select('id', 'name', 'code')
                    ->get()
                    ->keyBy('id');
            });
            

            return view('pages.shipping.sea.canceled.table',compact('get','count','clients'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    function print_packing_list(Request $request){
        $id = $request->id;
        return view('pages.shipping.sea.containers.packing_list',compact('id'));
    }

    function print_delivery(Request $request){
        $id = $request->id;
        return view('pages.shipping.sea.containers.delivery',compact('id'));
    }

}
