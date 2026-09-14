<?php

namespace App\Http\Requests\Api\V1\RiderApp\VehicleModel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVehicleModelRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'vehicle_type_id' => ['nullable', 'integer', Rule::exists('vehicle_types', 'id')],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
