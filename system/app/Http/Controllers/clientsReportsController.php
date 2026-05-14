<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;
use App\Http\Controllers\clientsController;
use App\Http\Controllers\treasuryController;
use App\Http\Controllers\branchesController;
use Barryvdh\DomPDF\Facade\Pdf;
use Mpdf\Mpdf;

class clientsReportsController extends Controller
{

    public function deposit(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;

        if($currency){
            $get = $get->where('currency',$currency);
        }
        
        $get = $get->where('type','deposit');
        $get = $get->where('status','approved');
        $get = $get->whereNull('data');
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        return view('pages.reports.clients.deposit',compact('get','users','currency'));
    }

    public function pending(Request $request){
        $get = DB::table('clients_transactions');
        
        $type = $request->type;
        
        $get = $get->where('type',$type);
        $get = $get->where('status','pending');
        $get = $get->where('client_id',$request->id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        return view('pages.reports.clients.pending',compact('get','users','type'));
    }

    public function approveReject(Request $request){
        try {

            $response = null;
            
            DB::transaction(function () use ($request, &$response) {
                $clientsController = new clientsController();
                $treasuryController = new treasuryController();
                $branchesController = new branchesController();

                
                $id        = $request->id;
                $status    = $request->status;
                $type      = $request->type;
                
                $get = DB::table('clients_transactions')->where('id',$id)->where('type',$type)->first();

                if(!$get){
                    return;
                }
                
                if($type === 'transfer'){
                    $value     = $request->value;
                    // $exchange_rate     = $request->exchange_rate;
                    // $from_currency     = $request->from_currency;
                    // $to_currency       = $request->to_currency;
                    // $result            = $request->result;


                    DB::table('clients_transactions')->where('id',$id)->where('type',$type)->update([
                        'status'        => $status,
                        // 'value'         => $value,
                        // 'transfer_value'=> $result,
                        // 'exchange_rate' => $exchange_rate,
                        // 'currency'      => $from_currency,
                        // 'to_currency'   => $to_currency,
                    ]);

                    $clientsController->update_balance($get->client_id);
                }else{
                    $value     = $request->value;
                    
                    // remaining_balance and value are TEXT columns in MySQL — PHP 8
                    // rejects string + string, so coerce both to float here.
                    $remainingFloat = floatval($get->remaining_balance);
                    $valueFloat     = floatval($value);

                    if($type === 'deposit'){

                        if($status === 'approved'){
                            $treasuryController->insert($get->transaction_number,'deposit','plus',$get->auto_id,$get->data,$get->value,$get->currency,$get->commission,$get->branch,$get->notes,$get->client_id,$remainingFloat + $valueFloat);

                            if($get->commission > 0){
                                $treasuryController->insert($get->transaction_number,'deposit_commission','plus',$get->auto_id,json_encode(['type'=>'commission']),$get->commission,$get->currency,0,$get->branch,$get->notes,$get->client_id,0);
                            }
                        }
                    }

                    if($type === 'withdraw'){

                        if($status === 'approved'){
                            $treasuryController->insert($get->transaction_number,'withdraw','minus',$get->auto_id,$get->data,$value,$get->currency,$get->commission,$get->branch,$get->notes,$get->client_id,$remainingFloat - $valueFloat);

                            if($get->commission > 0){
                                $treasuryController->insert($get->transaction_number,'withdraw_commission','plus',$get->auto_id,json_encode(['type'=>'commission']),$get->commission,$get->currency,0,$get->branch,$get->notes,$get->client_id,0);
                            }
                        }
                    }

                    if($type === 'withdraw_commission'){

                        if($status === 'approved'){
                            $treasuryController->insert($get->transaction_number,'withdraw_commission','plus',$get->auto_id,json_encode(['type'=>'commission']),$value,$get->currency,$get->commission,$get->branch,$get->notes,$get->client_id,$remainingFloat - $valueFloat);

                            // if($get->commission > 0){
                            //     $treasuryController->insert($get->transaction_number,'withdraw_commission','plus',$get->auto_id,json_encode(['type'=>'commission']),$get->commission,$get->currency,0,$get->branch,$get->notes,$get->client_id,0);
                            // }
                        }
                    }

                    DB::table('clients_transactions')->where('id',$id)->where('type',$type)->update([
                        'status' => $status,
                        'value'  => $value
                    ]);

                    $clientsController->update_balance($get->client_id);
                    $clientsController->update_remaining_balance_old_data($get->client_id);
                    $branchesController->update_balance($get->branch,$get->currency);
                }

                // Log the status change AFTER all the side-effect writes have
                // completed inside the transaction. The auto_id pins this to a
                // specific transaction in clients_transactions.
                $this->logAudit(
                    'transaction_status_change',
                    'clients_transactions',
                    $get->auto_id,
                    [
                        'transaction_id'     => $id,
                        'client_id'          => $get->client_id,
                        'type'               => $type,
                        'previous_status'    => $get->status,
                        'new_status'         => $status,
                        'value'              => $request->value ?? $get->value,
                        'currency'           => $get->currency,
                        'branch'             => $get->branch,
                        'transaction_number' => $get->transaction_number,
                    ],
                    $status === 'approved' ? 'Approved pending transaction' : 'Rejected pending transaction'
                );

            });

            return $response;

        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
            return response()->json(['type' => 'error'],500);
        }
    }

    public function exp(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;

        if($currency){
            $get = $get->where('currency',$currency);
        }
        
        $get = $get->whereIn('type',['exp_deposit','exp_withdraw','exp_custom_withdraw','exp_custom_deposit']);
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });
        
        return view('pages.reports.clients.exp',compact('get','users','currency'));
    }

    public function all(Request $request){
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

        $get = $get->where('status','approved');
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        // $users = Cache::remember('users', env("CACHE"), function () {
        //    return DB::table('users')->pluck('name', 'id');
        // });
        
        $client = DB::table('clients')->where('id',$request->client_id)->first();
        
        return view('pages.reports.clients.all',compact('get','currency','client'));
    }

    public function deposit_print(Request $request){
        $this->assertCanAccessClient($request->client_id);

        $get = DB::table('clients_transactions');
        $currency = $request->currency;

        if($currency){
            $get = $get->where('currency',$currency);
        }
        
        $get = $get->where('type','deposit');
        $get = $get->where('status','approved');
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans', // يدعم العربية
        ]);

        $html = view('pages.reports.clients.deposit_pdf',compact('get','users','currency'));

        $mpdf->WriteHTML($html);
        return response($mpdf->Output('xx.pdf', 'D'))
            ->header('Content-Type', 'application/pdf');
    }

    public function withdraw(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;

        if($currency){
            $get = $get->where('currency',$currency);
        }
        
        
        $get = $get->where('type','withdraw');
        $get = $get->where('status','approved');
        $get = $get->whereNull('data');
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        return view('pages.reports.clients.withdraw',compact('get','users','currency'));
    }

    public function withdraw_commission(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;

        if($currency){
            $get = $get->where('currency',$currency);
        }
        
        
        $get = $get->where('type','withdraw_commission');
        $get = $get->where('status','approved');
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        return view('pages.reports.clients.withdraw_commission',compact('get','users','currency'));
    }

    public function transfer(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;
        $to_currency = $request->to_currency;

        if($currency){
            $get = $get->where('currency',$currency);
        }

        if($to_currency){
            $get = $get->where('to_currency',$to_currency);
        }
        
        $get = $get->where('type','transfer');
    
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        return view('pages.reports.clients.transfer',compact('get','users','currency'));
    }


    public function transfer_clients(Request $request){
        $get = DB::table('clients_transactions');
        $currency = $request->currency;
        $to_currency = $request->to_currency;

        if($currency){
            $get = $get->where('currency',$currency);
        }
        
        $get = $get->whereIn('type',['deposit','withdraw']);
        $get = $get->whereNotNull('data');
    
        $get = $get->where('client_id',$request->client_id);
        // $get = $get->orderBy('id','DESC');
        $get = $get->get();

        $users = Cache::remember('users', env("CACHE"), function () {
           return DB::table('users')->pluck('name', 'id');
        });

        return view('pages.reports.clients.transfer_clients',compact('get','users','currency'));
    }
}
