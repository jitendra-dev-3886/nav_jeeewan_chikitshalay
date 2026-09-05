<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D/', '', (string) $this->phone);
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        $this->merge(['phone' => $phone, 'name' => trim((string) $this->name)]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:100'], 'phone' => ['required', 'regex:/^[6-9][0-9]{9}$/'],
            'age_group' => ['required', Rule::in(['under_18', '18_35', '36_60', 'over_60'])],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other', 'prefer_not'])],
            'service_id' => ['required', 'integer', 'exists:services,id'], 'starts_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'], 'follow_up' => ['boolean'], 'consent' => ['required', 'accepted'],
            'communication' => ['required', Rule::in(['none', 'email'])], 'email' => ['nullable', 'required_if:communication,email', 'email', 'max:150'],
            'website' => ['nullable', 'max:0'], 'source' => ['nullable', Rule::in(['phone', 'walk_in'])]];
    }
}
