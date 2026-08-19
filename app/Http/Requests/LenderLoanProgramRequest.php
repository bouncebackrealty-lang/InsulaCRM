<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LenderLoanProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'builders_risk_insurance' => $this->boolean('builders_risk_insurance'),
        ]);
    }

    public function rules(): array
    {
        return [
            'program_name' => 'required|string|max:255',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'points' => 'nullable|numeric|min:0|max:100',
            'max_ltc' => 'nullable|numeric|min:0|max:100',
            'max_ltv' => 'nullable|numeric|min:0|max:100',
            'term_length' => 'nullable|string|max:255',
            'purchase_closing_cost_percent' => 'nullable|numeric|min:0|max:100',
            'builders_risk_insurance' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
