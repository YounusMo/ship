<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;


class settingsController extends Controller
{
    private $settings_file = __DIR__.'/settings.json';

    public function save(Request $request){
        $config = file_get_contents($this->settings_file);
        $sett = json_decode($config,true);

        // Capture before-values for the audit log so a change to company name,
        // address, tax_id, receipt_footer, or tracking_prefix is reviewable
        // later. Without this, an attacker who phished one admin session
        // could silently rewrite receipt footers (which is what counterparties
        // see) or change the tax id, and only save2/update_exchange were
        // logged. The print PIN is handled separately below — we never log
        // the hash, only the fact that it rotated.
        $tracked = ['timezone','email','company_name','address','phone','commercial_registry','tax_id','receipt_footer','tracking_prefix','client_transactions_default_pending','proforma_email_subject','proforma_email_body','proforma_reminder_subject','proforma_reminder_body'];
        $before = [];
        foreach ($tracked as $k) {
            $before[$k] = $sett[$k] ?? null;
        }
        $pinHashBefore = $sett['print_pin_hash'] ?? '';

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
            // Short alphanumeric (≤5 chars) shown on every tracking sticker
            // — uppercase + strip anything that wouldn't render cleanly on
            // a scanner / human-typed lookup.
            'tracking_prefix'     => mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '',
                                        (string) ($request->tracking_prefix ?? ($sett['tracking_prefix'] ?? ''))
                                     )),
            // Hashed PIN required for bulk-printing every sticker in a
            // container/trip. Blank submit -> preserve existing hash so
            // the field can be left empty in the form once set.
            'print_pin_hash'      => $sett['print_pin_hash'] ?? '',
            // When true, every new client deposit/withdraw is created in
            // status='pending' so an admin must explicitly approve it
            // before the ledger moves. When false (default), the old
            // direct-post behaviour applies. Stored as a real boolean so
            // the JS doesn't have to interpret 'true'/'false' strings.
            'client_transactions_default_pending' => filter_var(
                $request->client_transactions_default_pending ?? ($sett['client_transactions_default_pending'] ?? false),
                FILTER_VALIDATE_BOOLEAN
            ),
            // Proforma email templates. Operators can tweak the wording from
            // /settings; the sourcing controllers use these as defaults and
            // the same placeholder system ({link}, {number}, {client},
            // {total}, {company}) still applies on the send side.
            'proforma_email_subject'    => $request->proforma_email_subject    ?? ($sett['proforma_email_subject']    ?? ''),
            'proforma_email_body'       => $request->proforma_email_body       ?? ($sett['proforma_email_body']       ?? ''),
            'proforma_reminder_subject' => $request->proforma_reminder_subject ?? ($sett['proforma_reminder_subject'] ?? ''),
            'proforma_reminder_body'    => $request->proforma_reminder_body    ?? ($sett['proforma_reminder_body']    ?? ''),
        ];
        // Cap at 5 chars regardless of what was submitted.
        $data['tracking_prefix'] = substr($data['tracking_prefix'], 0, 5);

        $newPin = trim((string) ($request->print_pin ?? ''));
        if ($newPin !== '') {
            // Constrain to 4-8 digits; reject everything else so an
            // accidental long string doesn't silently become the PIN.
            if (!preg_match('/^\d{4,8}$/', $newPin)) {
                return redirect('/settings')->withErrors([
                    'print_pin' => 'PIN must be 4-8 digits.',
                ]);
            }
            $data['print_pin_hash'] = Hash::make($newPin);
        }

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
        $pinRotated = ($data['print_pin_hash'] ?? '') !== $pinHashBefore;
        // Only emit an audit row when something actually changed — avoids
        // flooding the log on save clicks that touched only the logo.
        $changed = array_keys(array_filter(
            $tracked,
            fn($k) => ($before[$k] ?? null) !== ($after[$k] ?? null)
        ));
        if (!empty($changed) || $logo || $pinRotated) {
            $this->logAudit(
                'settings_save',
                'settings',
                null,
                [
                    'before'      => $before,
                    'after'       => $after,
                    'changed'     => $changed,
                    'logo_updated'=> (bool) $logo,
                    // The PIN itself is never logged — only the rotation event.
                    // Reviewers can correlate by user_id + timestamp.
                    'pin_rotated' => $pinRotated,
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
            'tracking_prefix'     => $sett['tracking_prefix'] ?? '',
            'print_pin_hash'      => $sett['print_pin_hash'] ?? '',
            'client_transactions_default_pending' => $sett['client_transactions_default_pending'] ?? false,
            'proforma_email_subject'    => $sett['proforma_email_subject']    ?? '',
            'proforma_email_body'       => $sett['proforma_email_body']       ?? '',
            'proforma_reminder_subject' => $sett['proforma_reminder_subject'] ?? '',
            'proforma_reminder_body'    => $sett['proforma_reminder_body']    ?? '',
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
            'tracking_prefix'     => $sett['tracking_prefix'] ?? '',
            'print_pin_hash'      => $sett['print_pin_hash'] ?? '',
            'client_transactions_default_pending' => $sett['client_transactions_default_pending'] ?? false,
            'proforma_email_subject'    => $sett['proforma_email_subject']    ?? '',
            'proforma_email_body'       => $sett['proforma_email_body']       ?? '',
            'proforma_reminder_subject' => $sett['proforma_reminder_subject'] ?? '',
            'proforma_reminder_body'    => $sett['proforma_reminder_body']    ?? '',
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

        // Derive fallbacks so callers never have to nullcheck. Stored value
        // wins; if blank we fall back to something sensible derived from
        // the existing fields.
        $data['tracking_prefix'] = $data['tracking_prefix']
            ?? self::deriveBrandPrefix($data['company_name'] ?? '');
        if ($data['tracking_prefix'] === '') {
            $data['tracking_prefix'] = self::deriveBrandPrefix($data['company_name'] ?? '');
        }
        return $data;
    }

    /**
     * Path to the uploaded logo if one exists, or null. Used by PDF/HTML
     * brand-mark partials to decide between rendering an <img> vs the
     * fallback monogram.
     */
    public static function brandLogoPath(): ?string
    {
        $p = public_path('images/logo.png');
        return is_file($p) && filesize($p) > 0 ? $p : null;
    }

    /**
     * First letter of company_name uppercased — used as the monogram when
     * no logo is uploaded.
     */
    public static function brandInitial(array $settings): string
    {
        $name = trim((string) ($settings['company_name'] ?? ''));
        if ($name === '') return '?';
        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    /**
     * Derive a 2-3 letter prefix from the company_name initials. Used as
     * the default tracking_prefix when the operator hasn't picked one
     * explicitly. "MATAZ TRADING COMPANY" -> "MTC". Single-word names
     * fall back to the first 3 letters: "Shipnow" -> "SHI".
     */
    public static function deriveBrandPrefix(string $companyName): string
    {
        $name = trim($companyName);
        if ($name === '') return '';
        $words = preg_split('/\s+/', preg_replace('/[^A-Za-z0-9 ]+/', '', $name));
        $words = array_values(array_filter($words, fn($w) => $w !== ''));
        if (count($words) >= 2) {
            $initials = '';
            foreach (array_slice($words, 0, 3) as $w) {
                $initials .= mb_substr($w, 0, 1);
            }
            return mb_strtoupper($initials);
        }
        return mb_strtoupper(mb_substr($words[0] ?? '', 0, 3));
    }
}
