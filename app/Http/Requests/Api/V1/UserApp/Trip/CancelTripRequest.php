<?php

namespace App\Http\Requests\Api\V1\UserApp\Trip;

use Illuminate\Foundation\Http\FormRequest;

class CancelTripRequest extends FormRequest
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
            'cancellation_reason_id' => ['nullable', 'integer', 'exists:trip_cancellation_reasons,id'],
        ];
    }
}
