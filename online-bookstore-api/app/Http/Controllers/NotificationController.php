<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notifications for the signed-in user.
 *
 * The recipient is always taken from the bearer token — the client never
 * sends a user id.
 */
class NotificationController extends Controller
{
    /**
     * The user's notifications, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $perPage = min((int) $request->input('per_page', 50), 100);
        $notifications = $query->paginate($perPage);

        return ApiResponse::paginated(
            $notifications,
            NotificationResource::collection($notifications->items())->resolve(),
        );
    }

    /**
     * Create a notification for the signed-in user.
     *
     * The server owns `id` and `created_at` — anything the client sends for
     * those keys is ignored.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'read' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', 'max:50'],
            'order_id' => ['nullable', 'string', 'max:50'],
        ]);

        $notification = Notification::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'read' => (bool) ($validated['read'] ?? false),
            'type' => $validated['type'] ?? 'general',
            'order_id' => $validated['order_id'] ?? null,
        ]);

        return ApiResponse::success(
            new NotificationResource($notification),
            'Notification created.',
            status: 201,
        );
    }

    /**
     * Mark one of the user's own notifications as read.
     */
    public function markRead(Request $request, int $notification): JsonResponse
    {
        $model = Notification::where('user_id', $request->user()->id)->find($notification);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->update(['read' => true]);

        return ApiResponse::success(new NotificationResource($model));
    }

    /**
     * Mark all of the user's notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true]);

        return ApiResponse::success(null, 'All notifications marked as read.');
    }

    /**
     * Delete one of the user's own notifications.
     */
    public function destroy(Request $request, int $notification): JsonResponse
    {
        $model = Notification::where('user_id', $request->user()->id)->find($notification);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->delete();

        return ApiResponse::success(null, 'Notification deleted.');
    }
}
