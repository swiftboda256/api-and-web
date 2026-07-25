<?php

namespace App\Http\Requests\Api\V1\UserApp\Trip;

use Illuminate\Foundation\Http\FormRequest;

class IndexTripRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort_by_fare' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
