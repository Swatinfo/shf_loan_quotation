<?php

namespace App\Http\Controllers;

use App\Models\ShfNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function index()
    {
        $notifications = ShfNotification::forUser(auth()->id())
            ->with('loan')
            ->recent(100)
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    public function unreadCount(): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount(auth()->id());

        return response()->json(['count' => $count]);
    }

    public function markRead(ShfNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        $this->notificationService->markRead($notification);

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        $this->notificationService->markAllRead(auth()->id());

        return response()->json(['success' => true]);
    }
}
