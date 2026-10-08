<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;

/**
 * Creates in-app notification records (shown in the web bell menu and the mobile
 * Inbox). Push delivery is handled separately by the queued jobs that own each
 * notification type, so this class only persists — that keeps a single source of
 * push and avoids duplicate deliveries.
 */
class NotificationService
{
    public function notifyAdmins(string $type, string $title, string $body, array $data = []): void
    {
        User::whereHas('role', fn ($q) => $q->where('name', Role::ADMIN))
            ->where('is_active', true)
            ->get()
            ->each(fn (User $admin) => $this->notifyUser($admin, $type, $title, $body, $data));
    }

    public function notifyUser(User $user, string $type, string $title, string $body, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
