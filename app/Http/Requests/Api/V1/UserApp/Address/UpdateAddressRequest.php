<?php

namespace App\Http\Requests\Api\V1\UserApp\Address;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
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
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'latitude' => ['required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'numeric', 'between:-180,180'],
            'formatted_address' => ['nullable', 'string', 'max:255'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
