<?php

namespace App\Http\Requests\Api\V1\UserApp\SupportTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
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
            'category_id' => [
                'required',
                'integer',
                Rule::exists('support_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'trip_id' => [
                'nullable',
                'integer',
                Rule::exists('trips', 'id')->where(
                    fn ($query) => $query->where(
                        fn ($query) => $query->where('customer_id', $this->user()?->id)->orWhere('rider_id', $this->user()?->id)
                    )
                ),
            ],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['url', 'max:2048'],
        ];
    }
}
