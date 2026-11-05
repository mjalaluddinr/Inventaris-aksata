<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RedisNotificationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Predis\Connection\ConnectionException;

class NotificationController extends Controller
{
    public function count(Request $request, RedisNotificationService $notifications): JsonResponse
    {
        $user = $this->authorizedUser($request);

        try {
            $count = $notifications->unreadCount($user);
        } catch (ConnectionException|LockTimeoutException $exception) {
            return $this->unavailableResponse($request, $exception);
        }

        return response()->json(['count' => $count])
            ->header('Cache-Control', 'no-store, private');
    }

    public function read(Request $request, RedisNotificationService $notifications): JsonResponse
    {
        $user = $this->authorizedUser($request);

        try {
            $notificationsList = $notifications->readAndClear($user);
        } catch (ConnectionException|LockTimeoutException $exception) {
            return $this->unavailableResponse($request, $exception);
        }

        return response()->json(['notifications' => $notificationsList])
            ->header('Cache-Control', 'no-store, private');
    }

    private function authorizedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);

        $canAccessNotifications = match ($user->role) {
            'admin' => true,
            'laboran' => $user->canAccessAdminPanel(),
            'peminjam' => $user->can('access-user-portal'),
            default => false,
        };

        abort_unless($canAccessNotifications, 403);

        return $user;
    }

    private function unavailableResponse(
        Request $request,
        ConnectionException|LockTimeoutException $exception,
    ): JsonResponse {
        Log::warning('Notification request could not access Redis.', [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'exception' => $exception,
        ]);

        return response()->json([
            'message' => 'Layanan notifikasi sedang tidak tersedia.',
        ], 503)->header('Cache-Control', 'no-store, private');
    }
}
