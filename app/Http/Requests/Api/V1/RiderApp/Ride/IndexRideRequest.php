<?php

namespace App\Http\Requests\Api\V1\RiderApp\Ride;

use Illuminate\Foundation\Http\FormRequest;

class IndexRideRequest extends FormRequest
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
            'status' => ['nullable', 'in:requested,searching,accepted,arrived,in_progress,completed,cancelled'],
            'type' => ['nullable', 'in:ride,delivery'],
            'min_fare' => ['nullable', 'numeric', 'min:0'],
            'max_fare' => ['nullable', 'numeric', 'min:0', 'gte:min_fare'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
