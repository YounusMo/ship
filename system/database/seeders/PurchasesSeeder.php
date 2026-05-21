<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Purchases\Enums\MarginType;
use App\Modules\Purchases\Enums\RateStatus;
use App\Modules\Purchases\Models\ExchangeRate;
use App\Modules\Purchases\Models\ExchangeRateConfig;
use App\Modules\Purchases\Models\Warehouse;
use Illuminate\Database\Seeder;

class PurchasesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWarehouses();
        $this->seedExchangeRateConfigs();
        $this->seedInitialRates();

        $this->command->info('✅ Purchases module seeded successfully');
        $this->command->line('   - 3 warehouses (YIWU, IST, DXB)');
        $this->command->line('   - 3 exchange rate configs (USD→CNY, USD→TRY, USD→AED)');
        $this->command->line('   - 3 initial exchange rates');
        $this->command->newLine();
        $this->command->line('💡 Next: create buyers and assign them to warehouses');
    }

    private function seedWarehouses(): void
    {
        $warehouses = [
            [
                'code' => 'YIWU',
                'name' => 'مستودع ييوو',
                'name_en' => 'Yiwu Warehouse',
                'country' => 'China',
                'city' => 'Yiwu',
                'address' => 'Yiwu International Trade City, Zhejiang',
                'local_currency' => 'CNY',
                'phone' => '+86-579-XXXXXXX',
                'manager_name' => 'مدير الصين',
            ],
            [
                'code' => 'IST',
                'name' => 'مستودع إسطنبول',
                'name_en' => 'Istanbul Warehouse',
                'country' => 'Turkey',
                'city' => 'Istanbul',
                'address' => 'Laleli, Fatih, Istanbul',
                'local_currency' => 'TRY',
                'phone' => '+90-212-XXXXXXX',
                'manager_name' => 'مدير تركيا',
            ],
            [
                'code' => 'DXB',
                'name' => 'مستودع دبي',
                'name_en' => 'Dubai Warehouse',
                'country' => 'UAE',
                'city' => 'Dubai',
                'address' => 'Dragon Mart, Dubai',
                'local_currency' => 'AED',
                'phone' => '+971-4-XXXXXXX',
                'manager_name' => 'مدير الإمارات',
            ],
        ];

        foreach ($warehouses as $data) {
            Warehouse::updateOrCreate(['code' => $data['code']], $data);
        }
    }

    private function seedExchangeRateConfigs(): void
    {
        $configs = [
            // USD → CNY: مستقرة، هامش 1.5%
            [
                'from_currency' => 'USD',
                'to_currency' => 'CNY',
                'source' => 'HYBRID',
                'primary_provider' => 'openexchangerates',
                'fallback_provider' => 'frankfurter',
                'margin_type' => MarginType::PERCENTAGE,
                'margin_value' => 1.5,
                'auto_update' => true,
                'update_interval_hours' => 6,
                'max_deviation_pct' => 5.00,
                'is_active' => true,
            ],
            // USD → TRY: متقلبة، هامش 3%
            [
                'from_currency' => 'USD',
                'to_currency' => 'TRY',
                'source' => 'HYBRID',
                'primary_provider' => 'openexchangerates',
                'fallback_provider' => 'frankfurter',
                'margin_type' => MarginType::PERCENTAGE,
                'margin_value' => 3.0,
                'auto_update' => true,
                'update_interval_hours' => 6,
                'max_deviation_pct' => 7.00,
                'is_active' => true,
            ],
            // USD → AED: شبه ثابتة (مربوطة بالدولار)، هامش 0.5%
            [
                'from_currency' => 'USD',
                'to_currency' => 'AED',
                'source' => 'HYBRID',
                'primary_provider' => 'openexchangerates',
                'fallback_provider' => 'frankfurter',
                'margin_type' => MarginType::PERCENTAGE,
                'margin_value' => 0.5,
                'auto_update' => true,
                'update_interval_hours' => 12,
                'max_deviation_pct' => 2.00,
                'is_active' => true,
            ],
        ];

        foreach ($configs as $data) {
            ExchangeRateConfig::updateOrCreate(
                [
                    'from_currency' => $data['from_currency'],
                    'to_currency' => $data['to_currency'],
                ],
                $data,
            );
        }
    }

    private function seedInitialRates(): void
    {
        $initialRates = [
            ['USD', 'CNY', '7.18500000', '1.5', '0.10778', '7.29278'],
            ['USD', 'TRY', '32.50000000', '3.0', '0.97500', '33.47500'],
            ['USD', 'AED', '3.67250000', '0.5', '0.01836', '3.69086'],
        ];

        foreach ($initialRates as [$from, $to, $rawRate, $margin, $marginAmount, $effective]) {
            $config = ExchangeRateConfig::query()
                ->where('from_currency', $from)
                ->where('to_currency', $to)
                ->first();

            if ($config === null) {
                continue;
            }

            // إذا في سعر نشط بالفعل، تخطّى
            $exists = ExchangeRate::query()
                ->where('config_id', $config->id)
                ->where('status', RateStatus::ACTIVE)
                ->exists();

            if ($exists) {
                continue;
            }

            ExchangeRate::create([
                'config_id' => $config->id,
                'from_currency' => $from,
                'to_currency' => $to,
                'raw_rate' => $rawRate,
                'raw_source' => 'manual',
                'raw_fetched_at' => now(),
                'margin_type' => MarginType::PERCENTAGE,
                'margin_value' => $margin,
                'margin_amount' => $marginAmount,
                'effective_rate' => $effective,
                'status' => RateStatus::ACTIVE,
                'valid_from' => now(),
            ]);
        }
    }
}
