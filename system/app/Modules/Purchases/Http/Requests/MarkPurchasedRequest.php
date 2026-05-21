<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkPurchasedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_amount' => ['required', 'numeric', 'min:0.01'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'invoice_image_url' => ['required', 'string', 'max:500'],
            'supplier_name' => ['nullable', 'string', 'max:200'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
