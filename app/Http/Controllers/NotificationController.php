<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RedisNotificationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Predis\Connection\ConnectionException;
use Predis\Connection\Resource\Exception\StreamInitException;

class NotificationController extends Controller
{
    public function count(Request $request, RedisNotificationService $notifications): JsonResponse
    {
        $user = $this->authorizedUser($request);

        try {
            $count = $notifications->unreadCount($user);
        } catch (ConnectionException|LockTimeoutException|StreamInitException $exception) {
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
        } catch (ConnectionException|LockTimeoutException|StreamInitException $exception) {
            return $this->unavailableResponse($request, $exception);
        }

        return response()->json(['notifications' => $notificationsList])
            ->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request): View
    {
        $user = $this->authorizedUser($request);
        $notificationService = app(RedisNotificationService::class);

        try {
            $notificationService->readAndClear($user);
            $notificationsList = $notificationService->activeNotifications($user);
        } catch (ConnectionException|LockTimeoutException|StreamInitException $exception) {
            Log::warning('Redis notification detail could not be loaded.', [
                'user_id' => $user->getKey(),
                'exception' => $exception,
            ]);
            $notificationsList = [];
        }

        $notifications = collect($notificationsList)->map(function (array $notification): array {
            $notification['message'] = app(RedisNotificationService::class)
                ->sanitizeNotificationMessage($notification['message']);
            $notification['created_at'] = Carbon::parse($notification['created_at']);

            return $notification;
        });
        $page = max(1, (int) $request->integer('page', 1));
        $notifications = new LengthAwarePaginator(
            $notifications->forPage($page, 10)->values(),
            $notifications->count(),
            10,
            $page,
            ['path' => $request->url(), 'pageName' => 'page', 'query' => $request->query()],
        );

        return view('pages.user.detail-notifikasi', [
            'notifications' => $notifications,
        ]);
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
        ConnectionException|LockTimeoutException|StreamInitException $exception,
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
