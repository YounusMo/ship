<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\langController;
use App\Http\Controllers\branchesController;
use App\Http\Controllers\settingsController;

class dataController extends Controller
{

    public $currencies = [];
    public $shipping_currencies = [];
    public $currency_exchange_rates = [];
    public $expenses = [];


    public function __construct(){
        $lang = new langController();

        $settingsController = new settingsController();
        $settings = $settingsController->get();

        $this->currencies = [ // For clients and branches
            [
                'code'   => 'usd',
                'text'   => $lang->write('USD'),
                'symbol' => '$',
            ],
            [
                'code'   => 'den',
                'text'   => $lang->write('LYD'),
                'symbol' => 'د.ل',
            ],
            [
                'code'   => 'eur',
                'text'   => $lang->write('Euro'),
                'symbol' => '€',
                'color'  => 'red'
            ],
            [
                'code'   => 'cny',
                'text'   => $lang->write('RMB'),
                'symbol' => '¥',
                'color'  => 'red'
            ],
        ];

        $this->shipping_currencies = [ // For shipping
            [
                'code'   => 'usd',
                'text'   => $lang->write('USD'),
                'symbol' => '$',
            ],
            [
                'code'   => 'den',
                'text'   => $lang->write('LYD'),
                'symbol' => 'د.ل',
            ],
        ];

        //Exchange rates
        $this->currency_exchange_rates = [
            'eur' => $settings['currency_eur'],
            'den' => $settings['currency_den'],
            'cny' => $settings['currency_cny'],
        ];

        $this->expenses = [
            ['val' => 'stationery_expenses', 'txt' => $lang->write('Stationery Expenses')],
            ['val' => 'catering_and_hospitality_expenses', 'txt' => $lang->write('Catering and Hospitality Expenses')],
            ['val' => 'cleaning_expenses', 'txt' => $lang->write('Cleaning Expenses')],
            ['val' => 'office_rent_expenses', 'txt' => $lang->write('Office Rent Expenses')],
            ['val' => 'warehouse_rent_expenses', 'txt' => $lang->write('Warehouse Rent Expenses')],
            ['val' => 'building_maintenance_expenses', 'txt' => $lang->write('Building Maintenance Expenses')],
            ['val' => 'equipment_maintenance_expenses', 'txt' => $lang->write('Equipment Maintenance Expenses')],
            ['val' => 'administrative_expenses', 'txt' => $lang->write('Administrative Expenses')],
            ['val' => 'service_expenses', 'txt' => $lang->write('Service Expenses')],
            ['val' => 'electricity_expenses', 'txt' => $lang->write('Electricity Expenses')],
            ['val' => 'water_expenses', 'txt' => $lang->write('Water Expenses')],
            ['val' => 'phone_and_internet_expenses', 'txt' => $lang->write('Phone and Internet Expenses')],
            ['val' => 'bank_commissions', 'txt' => $lang->write('Bank Commissions')],
            ['val' => 'salary', 'txt' => $lang->write('Salary')],
            ['val' => 'insurance', 'txt' => $lang->write('Insurance')],
            ['val' => 'other', 'txt' => $lang->write('Other')],
        ];
        
    }
    /* 
        Used Cache : suppliers - branches - branches_clients - clients - clients_ - users - users_ - branches_compant_accounting - clients_compant_accounting - containers_sea
    */

    public $code = 100; //Clients 
    public $tr_code = 1000; // Clients Transactions 
    public $supplier_code = 5000; // Clients Transactions 
    public $customs_code = 6000; // Customs broker Transactions 
    public $tr_br_code = 4000; // Branches Transactions 


    // الغرض من السحب في الشحن البحري
    public $sea_purpose = [
        'container_fee_value' => 'Container Fee Value',
        'container_loading' => 'Container Loading',
        'export_port_customs_fee' => 'Export Port Customs Fee',
        'import_port_customs_fee' => 'Import Port Customs Fee',
        'financial_penalties' => 'Financial Penalties',
        'unloading_loading_expenses' => 'Unloading and Loading Expenses',
        'transportation_expenses' => 'Transportation Expenses',
        'packaging_fees' => 'Packaging Fees',
        'other_services' => 'Other Services',
    ];
    
    // الغرض من السحب في الشحن جوي
    public $sky_purpose = [
        'container_fee_value' => 'Trip Fee Value',
        'container_loading' => 'Trip Loading',
        'export_port_customs_fee' => 'Export Port Customs Fee',
        'import_port_customs_fee' => 'Import Port Customs Fee',
        'financial_penalties' => 'Financial Penalties',
        'unloading_loading_expenses' => 'Unloading and Loading Expenses',
        'transportation_expenses' => 'Transportation Expenses',
        'packaging_fees' => 'Packaging Fees',
        'other_services' => 'Other Services',
    ];

    // Reason-codes for the common client-side cash flows. Free-text `notes`
    // stays for the rare 5% of rows that need real context, but the operator
    // is forced to pick a reason from this list. Translations go through
    // langController::write() so AR / EN / ZH all show.
    public $client_deposit_purposes = [
        'cash_received'       => 'Cash received',
        'bank_transfer'       => 'Bank transfer received',
        'advance_payment'     => 'Advance against future shipment',
        'container_payment'   => 'Payment for specific container',
        'commission_refund'   => 'Commission refund',
        'prepayment_received' => 'Prepayment received',
        'fx_adjustment'       => 'FX adjustment',
        'correction'          => 'Balance correction',
        'other'               => 'Other',
    ];

    public $client_withdraw_purposes = [
        'cash_paid'           => 'Cash paid to client',
        'bank_transfer'       => 'Bank transfer to client',
        'goods_release'       => 'Withdrawal at delivery',
        'expense_allocation'  => 'Allocated expense',
        'commission'          => 'Commission charge',
        'fx_adjustment'       => 'FX adjustment',
        'correction'          => 'Balance correction',
        'other'               => 'Other',
    ];

    public $client_transfer_purposes = [
        'currency_exchange' => 'Currency exchange',
        'fx_adjustment'     => 'FX adjustment',
        'correction'        => 'Balance correction',
        'other'             => 'Other',
    ];

    public $client_client_transfer_purposes = [
        'pay_other_client_debt' => "Payment of another client's debt",
        'family_account_move'   => 'Move between linked accounts',
        'correction'            => 'Balance correction',
        'other'                 => 'Other',
    ];

    public $branch_deposit_purposes = [
        'opening_balance'  => 'Opening balance',
        'cash_injection'   => 'Cash injection from owner',
        'bank_transfer_in' => 'Bank transfer received',
        'fx_adjustment'    => 'FX adjustment',
        'correction'       => 'Balance correction',
        'other'            => 'Other',
    ];

    public $branch_commission_purposes = [
        'shipping_commission' => 'Shipping commission',
        'service_fee'         => 'Service fee',
        'fx_adjustment'       => 'FX adjustment',
        'correction'          => 'Balance correction',
        'other'               => 'Other',
    ];

    public $branch_transfer_purposes = [
        'currency_exchange' => 'Currency exchange',
        'fx_adjustment'     => 'FX adjustment',
        'correction'        => 'Balance correction',
        'other'             => 'Other',
    ];

    public $branch_fix_purposes = [
        'move_cash_between_branches' => 'Move cash between branches',
        'settle_internal_debt'       => 'Settle internal debt',
        'owner_drawing'              => 'Owner drawing',
        'owner_salary'               => 'Owner salary',
        'owner_loan_out'             => 'Loan to owner',
        'owner_loan_repayment'       => 'Loan repayment from owner',
        'owner_capital_in'           => 'Owner capital contribution',
        'correction'                 => 'Balance correction',
        'other'                      => 'Other',
    ];

    // Owner-specific purpose codes for treasury (branches_transactions) outflows.
    // Drawings reduce equity; salary is an expense; loan is a balance-sheet liability/asset.
    public $owner_purposes = [
        'owner_drawing'        => 'Owner drawing',
        'owner_salary'         => 'Owner salary',
        'owner_loan_out'       => 'Loan to owner',
        'owner_loan_repayment' => 'Loan repayment from owner',
        'owner_capital_in'     => 'Owner capital contribution',
    ];

    public $client_deposit_prepayment_purposes = [
        'prepayment_received' => 'Prepayment received',
    ];

    public $cash_count_purposes = [
        'cash_count_short' => 'Cash count: shortage adjustment',
        'cash_count_over'  => 'Cash count: overage adjustment',
    ];

    public $supplier_deposit_purposes = [
        'goods_payment'   => 'Payment for goods',
        'advance_payment' => 'Advance payment',
        'refund'          => 'Supplier refund',
        'fx_adjustment'   => 'FX adjustment',
        'correction'      => 'Balance correction',
        'other'           => 'Other',
    ];

    public $customs_broker_deposit_purposes = [
        'customs_clearance' => 'Customs clearance fee',
        'port_handling'     => 'Port handling fee',
        'penalty'           => 'Customs penalty',
        'fx_adjustment'     => 'FX adjustment',
        'correction'        => 'Balance correction',
        'other'             => 'Other',
    ];

    /**
     * Resolve a purpose code into a translatable human label. Falls back to
     * the raw code (so dropped/renamed entries still render something) and
     * to '-' for an empty input.
     */
    public function purposeLabel($code){
        if (empty($code)) {
            return '-';
        }
        $all = array_merge(
            $this->client_deposit_purposes,
            $this->client_withdraw_purposes,
            $this->client_transfer_purposes,
            $this->client_client_transfer_purposes,
            $this->branch_deposit_purposes,
            $this->branch_commission_purposes,
            $this->branch_transfer_purposes,
            $this->branch_fix_purposes,
            $this->supplier_deposit_purposes,
            $this->customs_broker_deposit_purposes,
            $this->owner_purposes,
            $this->client_deposit_prepayment_purposes,
            $this->cash_count_purposes,
            ['commission' => 'Commission charge']
        );
        $label = $all[$code] ?? $code;
        $lang = new langController();
        return $lang->write($label);
    }

    

    public $default_branches = [
        1 => [
            'number' => 20100,
            'char'   => 'T'         
        ],
        2 => [
            'number' => 20100,
            'char'   => 'M'         
        ],
        3 => [
            'number' => 40100,
            'char'   => 'G'         
        ],
    ];

   
    
    public function get_currencies(Request $request){
        $curs = $this->currencies;

        foreach ($curs as $key => $value) {
            if($value['code'] === $request->currency){
                return response()->json($value[$request->what]);
            }
        }
    }

    public $ship_from = [
        // [
        //     'val' => 'libya',
        //     'txt' => 'Libya',
        // ],
        [
            'val' => 'china',
            'txt' => 'China',
        ],
    ];

    public $countries = [
        'Libya'     => ['code'=>'+218','currency'=>'د.ل'],
        'Lebanon'   => ['code'=>'+961','currency'=>'ل.ل'],
        'Syria'     => ['code'=>'+963','currency'=>'ل.س'],
        'Algeria'   => ['code'=>'+213','currency'=>'د.ج'],
        'Bahrain'   => ['code'=>'+973','currency'=>'د.ب'],
        'Egypt'     => ['code'=>'+20','currency'=>'ج.م'],
        'Iraq'      => ['code'=>'+964','currency'=>'ع.د'],
        'Jordan'    => ['code'=>'+962','currency'=>'د.أ'],
        'Kuwait'    => ['code'=>'+965','currency'=>'د.ك'],
        'Mauritania'=> ['code'=>'+222','currency'=>'أ.م.أ'],
        'Morocco'   => ['code'=>'+212','currency'=>'د.م'],
        'Oman'      => ['code'=>'+968','currency'=>'ر.ع'],
        'Palestine' => ['code'=>'+970','currency'=>'ش.ج'],
        'Qatar'     => ['code'=>'+974','currency'=>'ر.ق'],
        'Saudi Arabia' => ['code'=>'+966','currency'=>'ر.س'],
        'Somalia'   => ['code'=>'+252','currency'=>'ش.ص'],
        'Sudan'     => ['code'=>'+249','currency'=>'ج.س'],
        'Tunisia'   => ['code'=>'+216','currency'=>'د.ت'],
        'United Arab Emirates' => ['code'=>'+971','currency'=>'د.إ'],
        'Yemen'     => ['code'=>'+967','currency'=>'ر.ي'],
    ];


    public function formatTime($seconds) {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;

        return str_pad($h, 2, "0", STR_PAD_LEFT) . ":" .
            str_pad($m, 2, "0", STR_PAD_LEFT) . ":" .
            str_pad($s, 2, "0", STR_PAD_LEFT);
    }


    public function load_ajax_element(Request $request){
        try {
            return view($request->element);
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }


    public function imgErr($file){
        // if(!file_exists($file)){
        //     return asset('images/noimg.png');
        // }else{
        //     return $file;
        // }
        return url('compressor/10/'.$file);
    }

    public static function get_data($arg , $from , $where , $id , $alt = ''){
        $id_ = $arg.$from.$where.$id;

        return  Cache::remember('data_'.$id_, env("CACHE"), function ()use($arg,$from,$where,$id,$alt) {
            $get = DB::table($from)->select($arg)->where($where,$id)->first();

            return $get->{$arg} ?? $alt;
        });
    }


    /**
     * Tables whose rows the soft-delete / permanent-delete / restore endpoints
     * are allowed to touch. Anything outside this set is rejected so that
     * $request->table cannot be turned into an arbitrary-table update sink.
     */
    private const DELETABLE_TABLES = [
        'clients',
        'suppliers',
        'customs_brokers',
        'branches',
        'users',
        'containers_sea',
        'containers_sky',
        'store_sea',
        'store_sky',
        'store_out_sea',
        'store_out_sky',
    ];

    /**
     * Tables that only `admin` may touch — even via the generic delete endpoints.
     * `branch_admin` does not get to disable/restore user accounts or whole branches.
     */
    private const ADMIN_ONLY_TABLES = ['users', 'branches'];

    private function assertCanMutateTable(string $table): void
    {
        if (!in_array($table, self::DELETABLE_TABLES, true)) {
            abort(422, 'Invalid table');
        }
        if (in_array($table, self::ADMIN_ONLY_TABLES, true)
            && !in_array(auth()->user()->type, ['admin'], true)) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Per-row tenant check for the bulk delete endpoints. branch_admin should
     * only ever soft-delete records that belong to their own branch — without
     * this guard, a branch_admin could POST /del_recs with ids belonging to
     * clients/suppliers/customs_brokers/store_* rows in another branch and the
     * mass-update would happily oblige.
     *
     * Implementation is best-effort: if the table has a `branch` column we
     * verify every id resolves to the caller's branch; if not, we fall through
     * unchanged (admin-only tables are already blocked by assertCanMutateTable).
     */
    private function assertRowsInCallerBranch(string $table, array $ids): void
    {
        $user = auth()->user();
        if (!$user || $user->type === 'admin') {
            return;
        }
        if (empty($ids)) {
            return;
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn($table, 'branch')) {
            return;
        }
        $callerBranch = (int) $user->branch;
        $foreignCount = DB::table($table)
            ->whereIn('id', $ids)
            ->where(function ($q) use ($callerBranch) {
                $q->where('branch', '!=', $callerBranch)
                  ->orWhereNull('branch');
            })
            ->count();
        if ($foreignCount > 0) {
            abort(403, 'Cannot mutate records outside your branch');
        }
    }

    public function del_recs(Request $request){
        $this->assertCanMutateTable((string) $request->table);
        $checkIds = json_decode((string) $request->ids, true);
        if (is_array($checkIds) && !empty($checkIds)) {
            $this->assertRowsInCallerBranch((string) $request->table, $checkIds);
        }

        DB::transaction(function() use($request){
            try {

                $ids = json_decode($request->ids , true);

                if(count($ids) > 0){

                    if($request->table === 'customs_brokers'){
                        DB::table($request->table)->whereIn('id',$ids)->update([
                            'deleted'=>'true',
                            'not_active'=>'true',
                        ]);
                        
                        $tr = DB::table('customs_brokers_transactions')->whereIn('broker_id',$ids)->get();

                        foreach ($tr as $key => $value) {
                            DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->delete();
                        }
                        
                        DB::table('customs_brokers_transactions')->whereIn('broker_id',$ids)->delete();

                        $branchesController = new branchesController();
                        $curs = $this->currencies;
                        
                        $branches = DB::table('branches')->select('id')->where('deleted','false')->get();

                        foreach ($branches as $key => $value) {
                            foreach ($curs as $cur) {
                                $branchesController->update_balance($value->id,$cur['code']);
                            }
                        }
                    }

                    if($request->table === 'suppliers'){
                        DB::table($request->table)->whereIn('id',$ids)->update([
                            'deleted'=>'true',
                            'not_active'=>'true',
                        ]);
                        
                        $tr = DB::table('suppliers_transactions')->whereIn('supplier_id',$ids)->get();

                        foreach ($tr as $key => $value) {
                            DB::table('treasury_transactions')->where('transaction_number',$value->transaction_number)->delete();
                        }
                        
                        DB::table('suppliers_transactions')->whereIn('supplier_id',$ids)->delete();

                        $branchesController = new branchesController();
                        $curs = $this->currencies;

                        $branches = DB::table('branches')->select('id')->where('deleted','false')->get();

                        foreach ($branches as $key => $value) {
                            foreach ($curs as $cur) {
                                $branchesController->update_balance($value->id,$cur['code']);
                            }
                        }
                    }

                    if($request->table === 'clients'){
                        DB::table($request->table)->whereIn('id',$ids)->update([
                            'deleted'=>'true',
                            'not_active'=>'true',
                        ]);

                        DB::table('clients_transactions')->whereIn('client_id',$ids)->delete();
                        DB::table('treasury_transactions')->whereIn('client_id',$ids)->delete();
                        DB::table('store_out_sea')->whereIn('client_id',$ids)->delete();
                        DB::table('store_sea')->whereIn('client_id',$ids)->delete();
                        DB::table('store_out_sky')->whereIn('client_id',$ids)->delete();
                        DB::table('store_sky')->whereIn('client_id',$ids)->delete();

                        $branchesController = new branchesController();
                        $curs = $this->currencies;

                        $branches = DB::table('branches')->select('id')->where('deleted','false')->get();

                        foreach ($branches as $key => $value) {
                            foreach ($curs as $cur) {
                                $branchesController->update_balance($value->id,$cur['code']);
                            }
                        }
                    }else{
                        DB::table($request->table)->whereIn('id',$ids)->update(['deleted'=>'true']);
                    }

                    $this->logAudit(
                        'soft_delete_bulk',
                        $request->table,
                        null,
                        ['ids' => $ids],
                        'Bulk soft-delete on ' . $request->table
                    );
                }

            } catch (\Throwable $th) {
                Log::error($th->getMessage(), [
                    'exception' => $th,
                ]);
            }
        });
    }

    public function restore_recs(Request $request){
        $this->assertCanMutateTable((string) $request->table);

        DB::transaction(function() use($request){
            try {

                $ids = json_decode($request->ids , true);

                if(count($ids) > 0){
                    DB::table($request->table)->whereIn('id',$ids)->update(['deleted'=>'false']);

                    $this->logAudit(
                        'restore_bulk',
                        $request->table,
                        null,
                        ['ids' => $ids],
                        'Bulk restore on ' . $request->table
                    );
                }

            } catch (\Throwable $th) {
                Log::error($th->getMessage(), [
                    'exception' => $th,
                ]);
            }
        });

    }

    public function del_recs_permanent(Request $request){
        $this->assertCanMutateTable((string) $request->table);

        DB::transaction(function() use($request){
            try {

                $ids = json_decode($request->ids , true);

                if(count($ids) > 0){
                    DB::table($request->table)->whereIn('id',$ids)->update([
                        'not_active' => 'true'
                    ]);

                    $this->logAudit(
                        'permanent_delete_bulk',
                        $request->table,
                        null,
                        ['ids' => $ids],
                        'Bulk permanent-delete (not_active) on ' . $request->table
                    );
                }

                if($request->table === 'clients'){

                    // DB::table('clients_transactions')->whereIn('client_id',$ids)->delete();
                    // DB::table('treasury_transactions')->whereIn('client_id',$ids)->delete();
                    // DB::table('store_out_sea')->whereIn('client_id',$ids)->delete();
                    // DB::table('store_sea')->whereIn('client_id',$ids)->delete();

                    // $branchesController = new branchesController();
                    // $curs = $this->currencies;

                    // $branches = DB::table('branches')->select('id')->where('deleted','false')->get();

                    // foreach ($branches as $key => $value) {
                    //     foreach ($curs as $cur) {
                    //         $branchesController->update_balance($value->id,$cur['code']);
                    //     }
                    // }

                }

            } catch (\Throwable $th) {
                Log::error($th->getMessage(), [
                    'exception' => $th,
                ]);
            }
        });
        
    }

    public static function getFullUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                    || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

        $host = $_SERVER['HTTP_HOST'];
        $requestUri = $_SERVER['REQUEST_URI'];

        return $protocol . $host . $requestUri;
    }

    public static function countries(){
        $countries = [
            "Afghanistan", "Albania", "Algeria", "Andorra", "Angola",
            "Antigua and Barbuda", "Argentina", "Armenia", "Australia", "Austria",
            "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados",
            "Belarus", "Belgium", "Belize", "Benin", "Bhutan",
            "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei",
            "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia",
            "Cameroon", "Canada", "Central African Republic", "Chad", "Chile",
            "China", "Colombia", "Comoros", "Congo (Congo-Brazzaville)", "Costa Rica",
            "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark",
            "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt",
            "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini",
            "Ethiopia", "Fiji", "Finland", "France", "Gabon",
            "Gambia", "Georgia", "Germany", "Ghana", "Greece",
            "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana",
            "Haiti", "Honduras", "Hungary", "Iceland", "India",
            "Indonesia", "Iran", "Iraq", "Ireland", "Israel",
            "Italy", "Ivory Coast", "Jamaica", "Japan", "Jordan",
            "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan",
            "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia",
            "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar",
            "Malawi", "Malaysia", "Maldives", "Mali", "Malta",
            "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia",
            "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco",
            "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal",
            "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria",
            "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan",
            "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay",
            "Peru", "Philippines", "Poland", "Portugal", "Qatar",
            "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia",
            "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia",
            "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore",
            "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa",
            "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan",
            "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan",
            "Tajikistan", "Tanzania", "Thailand", "Timor-Leste", "Togo",
            "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan",
            "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom",
            "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City",
            "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"
        ];

        return $countries ;

    }

    public function formatPhoneNumber($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Match the different parts of the phone number
        preg_match('/(\d{0,3})(\d{0,3})(\d{0,4})/', $phone, $matches);
        
        // Format the phone number according to the matches
        if (!isset($matches[2])) {
            return $matches[1];
        } else {
            $formattedPhone = '(' . $matches[1] . ') ' . $matches[2];
            if (isset($matches[3])) {
                $formattedPhone .= '-' . $matches[3];
            }
            return $formattedPhone;
        }
    }


    public function compress($qualtiy, $path){
        $fullPath = $path;

        if (!file_exists($fullPath)) {
            abort(404);
        }

        $mime = mime_content_type($fullPath);
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($fullPath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($fullPath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($fullPath);
                break;
            default:
                abort(415, 'Unsupported image type');
        }

        return response()->stream(function () use ($image,$qualtiy) {
            imagejpeg($image, null, $qualtiy); // جودة منخفضة
            imagedestroy($image);
        }, 200, ['Content-Type' => 'image/jpeg']);
    }


    public function sys_selector($name,$data,$value = '',$all = false , $display_txt = ''){
        /* 
            Example : 
            $countries = [
                [
                    'val' => 'syria',
                    'txt' => 'Syria',
                ],
                [
                    'val' => 'libya',
                    'txt' => 'Libya',
                ],
                [
                    'val' => 'china',
                    'txt' => 'China',
                ],
            ];
        */

        return view('components.sys_selector',compact('name','data','value','all','display_txt'));
    }

    public function numberFormat($number, $comma = true){
        // Two-decimal "money" formatting. The earlier `null` decimals argument
        // tripped a PHP 8 deprecation and effectively dropped fractions.
        $number = floatval($number);
        return number_format($number, 2, '.', $comma ? ',' : '');
    }

    public function transaction_number($action,$id){
        $user = auth()->user()->id;
        $rand = $action.'_'.$id.'_'.$user.'_'.date('Ymd').'_'.date('His').rand(1,9999).uniqid();

        return $rand;
    }

    public function get_cur($cur,$display){
        $curs = $this->currencies;
        
        foreach ($curs as $key => $value) {
            if($value['code'] === $cur){
                return $value[$display];
                break;
            }
        }
    }

    public function get_type($type , $plus_minus = null , $data = null){
        $lang = new langController();
        $res = null;

        switch ($type) {
            case 'deposit_commission':
                $res = $lang->write('Commission');
            break;
            case 'transfer':
                $res = $lang->write('Currency convert');
            break;
            case 'supplier_deposit':
                $res = $lang->write('Deposit for shipping line');
            break;
            case 'exp_custom_deposit':
                $res = $lang->write('Shipping fees deposit');
            break;
            case 'exp_withdraw':
                $res = $lang->write('Shipping fees withdraw');
            break;
            case 'exp_deposit':
                $res = $lang->write('Shipping fees deposit');
            break;
            case 'exp_custom_withdraw':
                $res = $lang->write('Shipping fees withdraw');
            break;
            case 'custom_container_deposit':
                $res = $lang->write('Deposit for custom broker');
            break;
            case 'customs_deposit':
                $res = $lang->write('Deposit for custom broker');
            break;
            case 'withdraw_commission':
                $res = $lang->write('Commission');
            break;
            case 'deposit':
                $res = $lang->write('Deposit');
            break;
            case 'expenses_branch':
                $res = $lang->write('Expenses');
            break;
            case 'withdraw':
                $res = $lang->write('Withdraw');
            break;
            case 'transfer_branch':
                if($plus_minus === 'plus'){
                    $res = $lang->write('Deposit');
                }

                if($plus_minus === 'minus'){
                    $res = $lang->write('Withdraw');    
                }
            break;
            case 'branch_withdraw':
                $res = $lang->write('Withdraw');  
            break;
            case 'branch_deposit':
                $data = json_decode($data , true);
                if(isset($data['type']) && $data['type'] === 'commission'){
                    $res = $lang->write('Commission');
                }else{
                    $res = $lang->write('Deposit');  
                }
            break;
        }

        return $res;
    }

    public function get_client($id , $arg){
        return  Cache::remember('client_'.$id, env("CACHE"), function ()use($id,$arg) {
            $get = DB::table('clients')->select($arg)->where('id',$id)->first();

            return $get->{$arg} ?? '-';
        });
    }

}
