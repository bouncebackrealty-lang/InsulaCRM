<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize comma-separated text inputs into arrays before validation.
     */
    protected function prepareForValidation(): void
    {
        foreach (['preferred_states', 'preferred_zip_codes'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $values = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
                $this->merge([$field => $values]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'max_purchase_price' => 'nullable|numeric',
            'preferred_property_types' => 'nullable|array',
            'preferred_zip_codes' => 'nullable|array',
            'preferred_states' => 'nullable|array',
            'notes' => 'nullable|string',
        ];
    }
}
