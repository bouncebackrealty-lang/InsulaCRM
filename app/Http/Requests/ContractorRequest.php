<?php

namespace App\Http\Requests;

use App\Models\Contractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ensure specialty is always an array before validation.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('specialty');

        if (is_string($value)) {
            $values = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
            $this->merge(['specialty' => $values]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'specialty' => 'nullable|array',
            'specialty.*' => ['string', Rule::in(array_keys(Contractor::TRADE_CATEGORIES))],
            'service_area' => 'nullable|string|max:255',
            'priority' => ['required', Rule::in(array_keys(Contractor::PRIORITIES))],
            'referral_source' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(array_keys(Contractor::STATUSES))],
            'notes' => 'nullable|string',
        ];
    }
}
