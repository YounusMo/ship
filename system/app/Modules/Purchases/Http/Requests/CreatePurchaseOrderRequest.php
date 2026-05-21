<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Requests;

use App\Modules\Purchases\Enums\CommissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO: استبدل بـ logic فعلية حسب نظام الصلاحيات
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],

            'purchase_currency' => ['required', 'string', 'size:3', Rule::in(['CNY', 'TRY', 'AED', 'USD', 'EUR'])],
            'customer_currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'LYD', 'TRY', 'EUR'])],

            'commission_type' => ['required', new Enum(CommissionType::class)],
            'commission_value' => ['nullable', 'numeric', 'min:0', 'max:9999.9999'],
            'commission_notes' => ['nullable', 'string', 'min:10', 'max:1000'],

            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'contact_source' => ['nullable', 'string', 'max:50'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_name' => ['required', 'string', 'min:2', 'max:200'],
            'items.*.product_name_ar' => ['nullable', 'string', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.product_url' => ['nullable', 'url', 'max:500'],
            'items.*.image_url' => ['nullable', 'url', 'max:500'],
            'items.*.supplier_name' => ['nullable', 'string', 'max:200'],
            'items.*.supplier_url' => ['nullable', 'url', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
            'items.*.color' => ['nullable', 'string', 'max:50'],
            'items.*.size' => ['nullable', 'string', 'max:50'],
            'items.*.variant' => ['nullable', 'string', 'max:200'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
        ];
    }

    /**
     * تحقق متقدم بعد الـ rules الأساسية
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('commission_type');
            $value = $this->input('commission_value');
            $notes = $this->input('commission_notes');

            // NONE: notes إجباري
            if ($type === CommissionType::NONE->value && empty($notes)) {
                $validator->errors()->add(
                    'commission_notes',
                    'commission_notes مطلوبة عند اختيار "بدون عمولة"',
                );
            }

            // PERCENTAGE / FIXED_AMOUNT: value إجباري
            if (in_array($type, [CommissionType::PERCENTAGE->value, CommissionType::FIXED_AMOUNT->value], true)) {
                if ($value === null || $value === '') {
                    $validator->errors()->add(
                        'commission_value',
                        'commission_value مطلوبة',
                    );
                }
            }

            // PERCENTAGE: لا تتجاوز 100
            if ($type === CommissionType::PERCENTAGE->value && $value !== null && (float) $value > 100) {
                $validator->errors()->add(
                    'commission_value',
                    'نسبة العمولة لا تتجاوز 100%',
                );
            }
        });
    }
}
