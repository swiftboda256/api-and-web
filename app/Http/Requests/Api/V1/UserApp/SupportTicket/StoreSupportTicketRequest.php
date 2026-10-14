<?php

namespace App\Http\Requests\Api\V1\UserApp\SupportTicket;

use Illuminate\Database\Query\Builder;
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
            // The vehicle trip (the booking's trip_id). Shared by both apps: a trip the user
            // drove, or one they have a ride or delivery booking on.
            'trip_id' => [
                'nullable',
                'integer',
                Rule::exists('trips', 'id')->where(fn ($query) => $this->ownTrip($query)),
            ],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['url', 'max:2048'],
        ];
    }

    /**
     * @param  Builder  $query  on trips
     */
    private function ownTrip(Builder $query): void
    {
        $userId = $this->user()?->id;

        $query->where(fn (Builder $query) => $query
            ->where('rider_id', $userId)
            ->orWhereExists(fn (Builder $query) => $query->from('trip_passengers')
                ->whereColumn('trip_passengers.trip_id', 'trips.id')
                ->where('trip_passengers.customer_id', $userId))
            ->orWhereExists(fn (Builder $query) => $query->from('delivery_details')
                ->whereColumn('delivery_details.trip_id', 'trips.id')
                ->where('delivery_details.sender_id', $userId)));
    }
}
