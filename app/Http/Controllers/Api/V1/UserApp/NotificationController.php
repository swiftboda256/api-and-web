<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Notification\DeleteNotificationRequest;
use App\Http\Requests\Api\V1\UserApp\Notification\IndexNotificationRequest;
use App\Http\Requests\Api\V1\UserApp\Notification\MarkAsReadNotificationRequest;
use App\Http\Resources\Api\V1\UserApp\NotificationCollection;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(IndexNotificationRequest $request, NotificationService $notificationService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new NotificationCollection($notificationService->list($user, $request->validated())));
    }

    public function markAsRead(MarkAsReadNotificationRequest $request, NotificationService $notificationService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notificationIds = $request->validated('ids');
        $notificationService->markAsRead($user, $notificationIds);

        return self::success(message: $notificationIds ? 'Notifications marked as read.' : 'All notifications marked as read.');
    }

    public function destroy(DeleteNotificationRequest $request, NotificationService $notificationService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notificationService->delete($user, $request->validated('ids'));

        return self::success(message: 'Notifications deleted.');
    }
}
