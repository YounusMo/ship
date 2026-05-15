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
                    $response = response()->json(['type' => 'not_found'], 404);
                    return;
                }

                // Approving a pending row is a mutation just like creating a
                // new one, so the same period-close lock applies. Without this
                // an admin could defer approval until after month-end and
                // bypass the lock entirely.
                $this->assertPeriodOpen($get->created_date);
                
                if($type === 'transfer'){
                    DB::table('clients_transactions')->where('id',$id)->where('type',$type)->update([
                        'status' => $status,
                    ]);

                    $clientsController->update_balance($get->client_id);

                    // F2 fix: also post the matching cash entries so per-currency
                    // trial balance stays balanced. We model a client currency
                    // transfer as the company "buying" the from-currency back
                    // from the client and "selling" the to-currency to them —
                    // both legs cross treasury at the branch where the txn was
                    // booked, so the branch's per-currency cash stays whole.
                    //
                    // Trial-balance effect when balanced:
                    //   client.balance_from  decreases by `value`   (liability ↓)
                    //   branch.balance_from  decreases by `value`   (asset ↓)
                    //   client.balance_to    increases by `transfer_value`
                    //   branch.balance_to    increases by `transfer_value`
                    // Any rate spread vs current FX hits 5200 (FX gain/loss).
                    if ($status === 'approved' && !empty($get->branch)) {
                        $treasury = new \App\Http\Controllers\treasuryController();
                        try {
                            $treasury->recordCashMovement([
                                'transaction_number' => $get->transaction_number,
                                'type'               => 'transfer',
                                'plus_minus'         => 'minus',
                                'value'              => (float) $get->value,
                                'currency'           => $get->currency,
                                'branch'             => $get->branch,
                                'notes'              => $get->notes,
                                'purpose'            => $get->purpose,
                                'data'               => json_encode(['leg' => 'from', 'to_currency' => $get->to_currency]),
                                'auto_id'            => $get->auto_id,
                                'client_id'          => $get->client_id,
                            ]);
                            $treasury->recordCashMovement([
                                'transaction_number' => $get->transaction_number,
                                'type'               => 'transfer',
                                'plus_minus'         => 'plus',
                                'value'              => (float) $get->transfer_value,
                                'currency'           => $get->to_currency,
                                'branch'             => $get->branch,
                                'notes'              => $get->notes,
                                'purpose'            => $get->purpose,
                                'data'               => json_encode(['leg' => 'to', 'from_currency' => $get->currency]),
                                'auto_id'            => $get->auto_id,
                                'client_id'          => $get->client_id,
                            ]);
                        } catch (\Throwable $ex) {
                            // Don't fail the whole approval if recordCashMovement
                            // hits a schema mismatch; surface for follow-up.
                            \Illuminate\Support\Facades\Log::warning('transfer approval cash post failed: ' . $ex->getMessage());
                        }

                        // Receipt was deferred at creation time — issue it now.
                        $this->issueReceipt([
                            'source_table'       => 'clients_transactions',
                            'source_id'          => $get->id,
                            'transaction_number' => $get->transaction_number,
                            'auto_id'            => $get->auto_id,
                            'kind'               => 'transfer',
                            'currency'           => $get->currency,
                            'amount'             => $get->value,
                            'counterparty_type'  => 'client',
                            'counterparty_id'    => $get->client_id,
                            'branch_id'          => $get->branch,
                            'purpose'            => $get->purpose,
                            'notes'              => $get->notes,
                        ]);
                    }
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

                if ($response === null) {
                    $response = response()->json(['type' => 'success'], 200);
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

    /**
     * Client Statement of Account — printable PDF.
     * Route: GET /clients/reports/statement/{client_id}?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Shows opening balance, every approved transaction in the period,
     * running balance per currency, and closing balance. The single most
     * important document a Libyan trading firm produces for its clients
     * every month.
     */
    public function statement(Request $request, $client_id)
    {
        $this->assertCanAccessClient($client_id);

        $client = DB::table('clients')->where('id', $client_id)->first();
        if (!$client) {
            abort(404);
        }

        $from = $request->from ?: date('Y-m-01');
        $to   = $request->to   ?: date('Y-m-d');

        // Opening balance per currency: sum of approved transactions BEFORE $from
        $openingPerCcy = ['usd' => 0.0, 'eur' => 0.0, 'den' => 0.0, 'cny' => 0.0];
        $priorRows = DB::table('clients_transactions')
            ->where('status', 'approved')
            ->where('client_id', $client_id)
            ->where('created_date', '<', $from)
            ->get();
        foreach ($priorRows as $r) {
            $cur = $r->currency;
            if ($r->type === 'transfer') {
                if (isset($openingPerCcy[$cur])) {
                    $openingPerCcy[$cur] -= floatval($r->value);
                }
                if (isset($openingPerCcy[$r->to_currency])) {
                    $openingPerCcy[$r->to_currency] += floatval($r->transfer_value);
                }
                continue;
            }
            if (!isset($openingPerCcy[$cur])) continue;
            if ($r->plus_minus === 'plus') {
                $openingPerCcy[$cur] += floatval($r->value);
            } else {
                $openingPerCcy[$cur] -= floatval($r->value);
            }
        }

        // Period transactions: approved AND in range. Ordered chronologically.
        $periodRows = DB::table('clients_transactions')
            ->where('status', 'approved')
            ->where('client_id', $client_id)
            ->whereBetween('created_date', [$from, $to])
            ->orderBy('created_date', 'asc')
            ->orderBy('created_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Walk transactions, compute closing balance per currency.
        $closingPerCcy = $openingPerCcy;
        $runningPerCcy = $openingPerCcy;
        $rows = [];
        foreach ($periodRows as $r) {
            $cur = $r->currency;
            $debit = null;
            $credit = null;

            if ($r->type === 'transfer') {
                // Two effects: minus on `currency`, plus on `to_currency`.
                $runningPerCcy[$cur]              = ($runningPerCcy[$cur] ?? 0) - floatval($r->value);
                $runningPerCcy[$r->to_currency]   = ($runningPerCcy[$r->to_currency] ?? 0) + floatval($r->transfer_value);
                $debit = floatval($r->value);
            } else {
                if (!isset($runningPerCcy[$cur])) $runningPerCcy[$cur] = 0;
                if ($r->plus_minus === 'plus') {
                    $credit = floatval($r->value);
                    $runningPerCcy[$cur] += $credit;
                } else {
                    $debit = floatval($r->value);
                    $runningPerCcy[$cur] -= $debit;
                }
            }

            $rows[] = [
                'date'    => $r->created_date,
                'time'    => $r->created_time,
                'type'    => $r->type,
                'currency'=> $cur,
                'debit'   => $debit,
                'credit'  => $credit,
                'balance' => $runningPerCcy[$cur] ?? 0,
                'notes'   => $r->notes,
                'purpose' => $r->purpose ?? null,
                'auto_id' => $r->auto_id,
            ];

            $closingPerCcy = $runningPerCcy;
        }

        $settings = (new \App\Http\Controllers\settingsController())->get();
        $lang     = new \App\Http\Controllers\langController();
        $data     = new dataController();
        $branchName = $client->branch
            ? $lang->branch($client->branch)
            : '';

        $isRtl = (auth()->user()->lang ?? 'en') === 'ar';

        $html = view('pages.reports.clients.statement_pdf', compact(
            'client', 'from', 'to', 'openingPerCcy', 'closingPerCcy',
            'rows', 'settings', 'lang', 'data', 'branchName'
        ))->render();

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
            'margin_top'     => 12,
            'margin_bottom'  => 14,
            'margin_left'    => 12,
            'margin_right'   => 12,
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'statement-' . ($client->code ?: $client->id) . '-' . $from . '_' . $to . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
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
