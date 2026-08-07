<?php

namespace App\Http\Requests\Api\V1\UserApp\EmergencyContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'relationship' => ['required', Rule::in(['spouse', 'parent', 'sibling', 'family', 'child', 'friend', 'colleague', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
