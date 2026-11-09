<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Chat\IndexChatMessageRequest;
use App\Http\Requests\Api\V1\UserApp\Chat\SendChatMessageRequest;
use App\Http\Requests\Api\V1\UserApp\Chat\ShowChatRoomRequest;
use App\Http\Resources\Api\V1\UserApp\ChatMessageCollection;
use App\Http\Resources\Api\V1\UserApp\ChatMessageResource;
use App\Http\Resources\Api\V1\UserApp\ChatRoomResource;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function show(ShowChatRoomRequest $request, int $trip, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new ChatRoomResource($chatService->customerRoom($user, $trip, $request->validated('type'))));
    }

    public function messages(IndexChatMessageRequest $request, int $trip, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $messages = $chatService->customerMessages($user, $trip, $request->validated('type'), (int) ($request->validated('per_page') ?? 15));

        return self::success(new ChatMessageCollection($messages));
    }

    public function send(SendChatMessageRequest $request, int $trip, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $message = $chatService->sendAsCustomer($user, $trip, $request->validated('type'), $request->validated());

        return self::success(new ChatMessageResource($message), status: 201);
    }

    public function markRead(ShowChatRoomRequest $request, int $trip, ChatService $chatService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $chatService->markReadAsCustomer($user, $trip, $request->validated('type'));

        return self::success(['marked_read' => $count]);
    }
}
