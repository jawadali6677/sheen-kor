<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    use SerializesApiContent;

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest('created_at')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $notifications->getCollection()
                ->map(fn (DatabaseNotification $notification): array => $this->notificationPayload($notification))
                ->values(),
            'meta' => $this->paginationMeta($notifications),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json([
            'notification' => $this->notificationPayload($item),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationPayload(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        $payload = [
            'id' => $notification->id,
            'kind' => $data['kind'] ?? '',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];

        foreach ($data as $key => $value) {
            if (is_string($key) && str_ends_with($key, '_id')) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
