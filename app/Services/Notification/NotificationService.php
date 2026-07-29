<?php

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class NotificationService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        return $user->notifications()
            ->when($filters['status'] ?? null, fn ($query, $status) => $status === 'read' ? $query->read() : $query->unread())
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', 'like', "%{$type}"))
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array<int, string>|null  $notificationIds
     */
    public function markAsRead(User $user, ?array $notificationIds): void
    {
        if ($notificationIds !== null) {
            $user->notifications()->whereIn('id', $notificationIds)->whereNull('read_at')->update(['read_at' => now()]);

            return;
        }

        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * @param  array<int, string>  $notificationIds
     */
    public function delete(User $user, array $notificationIds): void
    {
        $user->notifications()->whereIn('id', $notificationIds)->delete();
    }
}
