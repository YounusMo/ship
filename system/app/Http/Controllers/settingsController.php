<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;


class settingsController extends Controller
{
    private $settings_file = __DIR__.'/settings.json';

    public function save(Request $request){
        $config = file_get_contents($this->settings_file);
        $sett = json_decode($config,true);
        
        $data = [
            'timezone'     => $request->timezone,
            'currency_eur' => $sett['currency_eur'],
            'currency_den' => $sett['currency_den'],
            'currency_cny' => $sett['currency_cny'],
            'email'        => $request->email,
            'company_name' => $request->company_name,
            'address'      => $request->address,
            'phone'        => $request->phone,
            'logo'         => '',
        ];

        

        $logo = $request->file('logo');
        if($logo){
            $logo->move(public_path('images'),'logo.png');
        }
        
        $data = json_encode($data,JSON_PRETTY_PRINT);

        file_put_contents($this->settings_file,$data);

        return redirect('/settings');
    }

    public function save2(Request $request){

        $config = file_get_contents($this->settings_file);
        $sett = json_decode($config,true);

        $before = [
            'currency_eur' => $sett['currency_eur'] ?? null,
            'currency_den' => $sett['currency_den'] ?? null,
            'currency_cny' => $sett['currency_cny'] ?? null,
        ];

        $data = [
            'timezone'      => $sett['timezone'],
            'logo'          => $sett['logo'],
            'company_name'  => $sett['company_name'],
            'address'       => $sett['address'],
            'phone'         => $sett['phone'],
            'email'         => $sett['email'],
            'currency_eur'  => floatval($request->currency_eur),
            'currency_den'  => floatval($request->currency_den),
            'currency_cny'  => floatval($request->currency_cny),
        ];

        $data_json = json_encode($data,JSON_PRETTY_PRINT);

        file_put_contents($this->settings_file,$data_json);

        $this->logAudit(
            'exchange_rate_change',
            'settings',
            null,
            [
                'before' => $before,
                'after'  => [
                    'currency_eur' => $data['currency_eur'],
                    'currency_den' => $data['currency_den'],
                    'currency_cny' => $data['currency_cny'],
                ],
            ],
            'Exchange rates updated (save2)'
        );

        return redirect('/settings');
    }

    public function update_exchange(Request $request){
        $config = file_get_contents($this->settings_file);
        $sett = json_decode($config,true);

        $before = [
            'currency_eur' => $sett['currency_eur'] ?? null,
            'currency_den' => $sett['currency_den'] ?? null,
            'currency_cny' => $sett['currency_cny'] ?? null,
        ];

        $data = [
            'timezone'      => $sett['timezone'],
            'logo'          => $sett['logo'],
            'company_name'  => $sett['company_name'],
            'address'       => $sett['address'],
            'phone'         => $sett['phone'],
            'email'         => $sett['email'],
            'currency_eur'  => floatval($request->currency_eur),
            'currency_den'  => floatval($request->currency_den),
            'currency_cny'  => floatval($request->currency_cny),
        ];

        // DB::select('update users set printer="'.$request->printer.'" where id="'.auth()->user()->id.'"');

        $data_json = json_encode($data,JSON_PRETTY_PRINT);

        file_put_contents($this->settings_file,$data_json);

        DB::table('users')->where('type','admin')->update([
            'updated_exchange' => date('Y-m-d')
        ]);

        $this->logAudit(
            'exchange_rate_change',
            'settings',
            null,
            [
                'before' => $before,
                'after'  => [
                    'currency_eur' => $data['currency_eur'],
                    'currency_den' => $data['currency_den'],
                    'currency_cny' => $data['currency_cny'],
                ],
            ],
            'Exchange rates updated (update_exchange)'
        );
    }

    public function get(){
        $config = file_get_contents($this->settings_file);
        $data = json_decode($config,true);
        return $data;
    }
}
