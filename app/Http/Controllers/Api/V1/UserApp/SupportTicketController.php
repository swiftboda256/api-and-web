<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\SupportTicket\IndexSupportTicketRequest;
use App\Http\Requests\Api\V1\UserApp\SupportTicket\StoreSupportTicketRequest;
use App\Http\Resources\Api\V1\UserApp\SupportTicketCollection;
use App\Http\Resources\Api\V1\UserApp\SupportTicketResource;
use App\Models\User;
use App\Services\SupportTicket\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(IndexSupportTicketRequest $request, SupportTicketService $supportTicketService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new SupportTicketCollection($supportTicketService->list($user, $request->validated())));
    }

    public function show(Request $request, int $id, SupportTicketService $supportTicketService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new SupportTicketResource($supportTicketService->find($user, $id)));
    }

    public function store(StoreSupportTicketRequest $request, SupportTicketService $supportTicketService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $supportTicketService->create($user, $request->validated());

        return self::success(new SupportTicketResource($ticket), status: 201);
    }
}
