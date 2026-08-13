<?php

namespace App\Http\Requests\Api\V1\UserApp\Trip;

use Illuminate\Foundation\Http\FormRequest;

class RateTripRequest extends FormRequest
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
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ];
    }
}
