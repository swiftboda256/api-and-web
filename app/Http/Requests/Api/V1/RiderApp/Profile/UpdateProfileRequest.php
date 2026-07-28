<?php

namespace App\Http\Requests\Api\V1\RiderApp\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'other_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'avatar_url' => ['nullable', 'url', 'max:2048'],

            'national_id_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'license_expiry_at' => ['sometimes', 'nullable', 'date'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],

            'documents' => ['sometimes', 'array'],
            'documents.*.type' => ['required_with:documents', 'in:national_id,driving_license'],
            'documents.*.file' => ['required_with:documents', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
