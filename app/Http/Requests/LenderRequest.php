<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'service_area' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
