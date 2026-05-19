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

        // Capture before-values for the audit log so a change to company name,
        // address, tax_id, or receipt_footer is reviewable later. Without
        // this, an attacker who phished one admin session could silently
        // rewrite receipt footers (which is what counterparties see) or
        // change the tax id, and only save2/update_exchange were logged.
        $tracked = ['timezone','email','company_name','address','phone','commercial_registry','tax_id','receipt_footer'];
        $before = [];
        foreach ($tracked as $k) {
            $before[$k] = $sett[$k] ?? null;
        }

        $data = [
            'timezone'            => $request->timezone,
            'currency_eur'        => $sett['currency_eur'],
            'currency_den'        => $sett['currency_den'],
            'currency_cny'        => $sett['currency_cny'],
            'email'               => $request->email,
            'company_name'        => $request->company_name,
            'address'             => $request->address,
            'phone'               => $request->phone,
            'commercial_registry' => $request->commercial_registry ?? ($sett['commercial_registry'] ?? ''),
            'tax_id'              => $request->tax_id              ?? ($sett['tax_id'] ?? ''),
            'receipt_footer'      => $request->receipt_footer      ?? ($sett['receipt_footer'] ?? ''),
            'logo'                => '',
        ];

        $logo = $request->file('logo');
        if($logo){
            $logo->move(public_path('images'),'logo.png');
        }

        $data_json = json_encode($data,JSON_PRETTY_PRINT);

        file_put_contents($this->settings_file,$data_json);

        $after = [];
        foreach ($tracked as $k) {
            $after[$k] = $data[$k] ?? null;
        }
        // Only emit an audit row when something actually changed — avoids
        // flooding the log on save clicks that touched only the logo.
        $changed = array_keys(array_filter(
            $tracked,
            fn($k) => ($before[$k] ?? null) !== ($after[$k] ?? null)
        ));
        if (!empty($changed) || $logo) {
            $this->logAudit(
                'settings_save',
                'settings',
                null,
                [
                    'before'      => $before,
                    'after'       => $after,
                    'changed'     => $changed,
                    'logo_updated'=> (bool) $logo,
                ],
                'General settings updated'
            );
        }

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
            'timezone'            => $sett['timezone'],
            'logo'                => $sett['logo'],
            'company_name'        => $sett['company_name'],
            'address'             => $sett['address'],
            'phone'               => $sett['phone'],
            'email'               => $sett['email'],
            'commercial_registry' => $sett['commercial_registry'] ?? '',
            'tax_id'              => $sett['tax_id'] ?? '',
            'receipt_footer'      => $sett['receipt_footer'] ?? '',
            'currency_eur'        => floatval($request->currency_eur),
            'currency_den'        => floatval($request->currency_den),
            'currency_cny'        => floatval($request->currency_cny),
        ];

        $data_json = json_encode($data,JSON_PRETTY_PRINT);

        file_put_contents($this->settings_file,$data_json);

        $this->snapshotFxRates($before, $data, 'save2');
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
            'timezone'            => $sett['timezone'],
            'logo'                => $sett['logo'],
            'company_name'        => $sett['company_name'],
            'address'             => $sett['address'],
            'phone'               => $sett['phone'],
            'email'               => $sett['email'],
            'commercial_registry' => $sett['commercial_registry'] ?? '',
            'tax_id'              => $sett['tax_id'] ?? '',
            'receipt_footer'      => $sett['receipt_footer'] ?? '',
            'currency_eur'        => floatval($request->currency_eur),
            'currency_den'        => floatval($request->currency_den),
            'currency_cny'        => floatval($request->currency_cny),
        ];

        $data_json = json_encode($data,JSON_PRETTY_PRINT);

        file_put_contents($this->settings_file,$data_json);

        DB::table('users')->where('type','admin')->update([
            'updated_exchange' => date('Y-m-d')
        ]);

        $this->snapshotFxRates($before, $data, 'update_exchange');
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

    /**
     * Append rows to fx_rate_history for every currency whose rate actually changed.
     * Silent on failure: the rate write itself already happened and the audit_log
     * captures the change, so a history-table error must not block the user.
     */
    private function snapshotFxRates(array $before, array $after, string $origin): void
    {
        if (!Schema::hasTable('fx_rate_history')) {
            return;
        }
        $user = auth()->user();
        $rows = [];
        foreach (['eur', 'den', 'cny'] as $c) {
            $b = $before['currency_' . $c] ?? null;
            $a = $after['currency_' . $c]  ?? null;
            if ($a === null) continue;
            if ($b !== null && abs((float) $b - (float) $a) < 0.000001) continue;
            $rows[] = [
                'currency'         => $c,
                'rate'             => (float) $a,
                'previous_rate'    => $b !== null ? (float) $b : null,
                'set_by_user_id'   => $user?->id,
                'set_by_user_name' => $user?->name,
                'effective_from'   => date('Y-m-d H:i:s'),
                'notes'            => 'via ' . $origin,
            ];
        }
        if (!empty($rows)) {
            try {
                DB::table('fx_rate_history')->insert($rows);
            } catch (\Throwable $e) {
                Log::warning('fx_rate_history snapshot failed: ' . $e->getMessage());
            }
        }
    }

    public function get(){
        $config = file_get_contents($this->settings_file);
        $data = json_decode($config,true);
        return $data;
    }
}
