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
            'avatar' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'availability_status' => ['sometimes', 'nullable', Rule::in(['online', 'offline'])],
            'home_zone_id' => ['sometimes', 'nullable', 'integer', 'exists:zones,id'],

            'national_id_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'license_expiry_at' => ['sometimes', 'nullable', 'date'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],

            'national_id' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'driving_license' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'latitude' => ['sometimes', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required_with:latitude', 'numeric', 'between:-180,180'],

            'vehicle_type_id' => ['sometimes', 'nullable', 'integer', 'exists:vehicle_types,id'],
            'make' => ['sometimes', 'nullable', 'string', 'max:255'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'color' => ['sometimes', 'nullable', 'string', 'max:255'],
            'plate_number' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('vehicles', 'plate_number')->ignore($this->user()?->riderProfile?->vehicle?->id)],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance_expiry_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
