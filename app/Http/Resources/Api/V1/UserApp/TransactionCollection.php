<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionCollection extends ResourceCollection
{
    public $collects = TransactionResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, Transaction> $paginator */
        $paginator = $this->resource;

        return [
            'transactions' => $this->collection,
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
