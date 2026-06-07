<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the canonical Libya operational network: Tripoli (national hub),
 * Misrata + Benghazi (regional hubs), and the spoke branches each one
 * feeds. Idempotent — safe to re-run; rows are matched on `code`.
 *
 * Codes are stable, UPPER_SNAKE, and match the keys the mobile employee
 * app uses when persisting "active branch" so the app's branch picker
 * round-trips against this list.
 */
class TrackingBranchesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $branches = [
            // Hubs
            ['code' => 'TRIPOLI_HUB',  'name' => 'طرابلس - المركز الرئيسي',  'name_en' => 'Tripoli HQ',     'role' => 'HUB',   'country' => 'LY', 'city' => 'Tripoli'],
            ['code' => 'MISRATA_HUB',  'name' => 'مصراتة - المستودع',        'name_en' => 'Misrata Hub',    'role' => 'HUB',   'country' => 'LY', 'city' => 'Misrata'],
            ['code' => 'BENGHAZI_HUB', 'name' => 'بنغازي - المستودع',         'name_en' => 'Benghazi Hub',   'role' => 'HUB',   'country' => 'LY', 'city' => 'Benghazi'],

            // West (Tripoli) spokes
            ['code' => 'TRIPOLI_TAJOURA',  'name' => 'طرابلس - تاجوراء',  'name_en' => 'Tripoli - Tajoura',  'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Tripoli'],
            ['code' => 'TRIPOLI_JANZUR',   'name' => 'طرابلس - جنزور',     'name_en' => 'Tripoli - Janzur',   'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Tripoli'],
            ['code' => 'ZAWIYA',           'name' => 'الزاوية',            'name_en' => 'Zawiya',             'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Zawiya'],
            ['code' => 'ZUWARA',           'name' => 'زوارة',              'name_en' => 'Zuwara',             'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Zuwara'],
            ['code' => 'GHARYAN',          'name' => 'غريان',              'name_en' => 'Gharyan',            'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Gharyan'],

            // Center (Misrata) spokes
            ['code' => 'SIRTE',            'name' => 'سرت',                'name_en' => 'Sirte',              'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Sirte'],
            ['code' => 'KHOMS',            'name' => 'الخمس',              'name_en' => 'Khoms',              'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Khoms'],
            ['code' => 'ZLITEN',           'name' => 'زليتن',              'name_en' => 'Zliten',             'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Zliten'],
            ['code' => 'BANI_WALID',       'name' => 'بني وليد',           'name_en' => 'Bani Walid',         'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Bani Walid'],

            // East (Benghazi) spokes
            ['code' => 'AJDABIYA',         'name' => 'أجدابيا',            'name_en' => 'Ajdabiya',           'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Ajdabiya'],
            ['code' => 'TOBRUK',           'name' => 'طبرق',               'name_en' => 'Tobruk',             'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Tobruk'],
            ['code' => 'AL_BAYDA',         'name' => 'البيضاء',            'name_en' => 'Al Bayda',           'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Al Bayda'],
            ['code' => 'DERNA',            'name' => 'درنة',               'name_en' => 'Derna',              'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Derna'],
            ['code' => 'SEBHA',            'name' => 'سبها',               'name_en' => 'Sebha',              'role' => 'SPOKE', 'country' => 'LY', 'city' => 'Sebha'],
        ];

        foreach ($branches as $b) {
            DB::table('tracking_branches')->updateOrInsert(
                ['code' => $b['code']],
                [
                    'name'       => $b['name'],
                    'name_en'    => $b['name_en'],
                    'role'       => $b['role'],
                    'country'    => $b['country'],
                    'city'       => $b['city'],
                    'is_active'  => 1,
                    'updated_at' => $now,
                    'created_at' => DB::raw('COALESCE(created_at, "' . $now->toDateTimeString() . '")'),
                ],
            );
        }
    }
}
