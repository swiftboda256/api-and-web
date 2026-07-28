<?php

namespace App\Http\Requests\Api\V1\RiderApp\Ride;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelRideRequest extends FormRequest
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
            'cancellation_reason_id' => [
                'nullable',
                'integer',
                Rule::exists('trip_cancellation_reasons', 'id')->where(
                    fn ($query) => $query->whereIn('applies_to', ['rider', 'both'])->where('is_active', true)
                ),
            ],
        ];
    }
}
