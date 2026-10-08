<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly FcmService $fcm) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);
        $unread = $request->user()->notifications()->whereNull('read_at')->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Notifications retrieved.',
            'data' => $notifications->through(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]),
            'meta' => ['unread_count' => $unread],
        ]);
    }

    /**
     * Register or refresh this device's FCM token and subscribe it to the
     * "announcements" topic so it receives announcement pushes.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required_without:fcm_token', 'string'],
            'fcm_token' => ['required_without:token', 'string'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        $token = $data['token'] ?? $data['fcm_token'];
        $user = $request->user();

        DeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? 'android',
                'last_used_at' => now(),
            ],
        );

        // Keep the legacy single-token column in sync for backwards compatibility.
        $user->update(['fcm_token' => $token]);

        $this->fcm->subscribeToTopic(FcmService::ANNOUNCEMENTS_TOPIC, [$token]);

        return response()->json([
            'status' => 'success',
            'message' => 'Device registered for announcements.',
            'data' => null,
        ]);
    }

    /**
     * Called on logout: unsubscribe and forget this device's token.
     */
    public function removeFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required_without:fcm_token', 'string'],
            'fcm_token' => ['required_without:token', 'string'],
        ]);

        $token = $data['token'] ?? $data['fcm_token'];
        $user = $request->user();

        $this->fcm->unsubscribeFromTopic(FcmService::ANNOUNCEMENTS_TOPIC, [$token]);
        DeviceToken::where('token', $token)->where('user_id', $user->id)->delete();

        if ($user->fcm_token === $token) {
            $user->update(['fcm_token' => null]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Device unregistered.',
            'data' => null,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
            'data' => ['unread_count' => 0],
        ]);
    }
}
