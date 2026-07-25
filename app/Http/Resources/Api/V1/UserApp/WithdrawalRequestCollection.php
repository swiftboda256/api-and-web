<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class WithdrawalRequestCollection extends ResourceCollection
{
    public $collects = WithdrawalRequestResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, WithdrawalRequest> $paginator */
        $paginator = $this->resource;

        return [
            'withdrawal_requests' => $this->collection,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
