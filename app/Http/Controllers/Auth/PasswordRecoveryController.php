<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordRecoveryRequests;
use App\Services\RedisNotificationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use UnexpectedValueException;

class PasswordRecoveryController extends Controller
{
    public function __construct(private RedisNotificationService $notifications) {}

    public function create(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function store(Request $request, PasswordRecoveryRequests $recoveryRequests): JsonResponse|RedirectResponse
    {
        // ASVS 2.2.1: Validate the username at the trusted request boundary.
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
        ]);

        $user = User::query()
            ->where('username', $validated['username'])
            ->where('is_active', true)
            ->first();

        try {
            // ASVS 2.3.1: Record the request separately; only an authorized admin approval sets the login flag.
            $recoveryRequests->submit($user);
        } catch (QueryException|LockTimeoutException|UnexpectedValueException $exception) {
            Log::error('Password recovery request could not be recorded.', [
                'user_id' => $user?->getKey(),
                'exception' => $exception,
            ]);

            $message = 'Permintaan belum dapat dicatat. Silakan coba kembali beberapa saat lagi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['username' => [$message]],
                ], 503);
            }

            return redirect()->route('password.request')->withErrors(['username' => $message]);
        }

        if ($user instanceof User) {
            try {
                $this->notifications->notifyAdministrators(
                    "Permintaan ganti password dari akun {$user->username}.",
                );
            } catch (Throwable $exception) {
                // ASVS 16.5.2: Preserve the database-backed request if the optional Redis notification is unavailable.
                Log::warning('Password recovery request could not notify administrators.', [
                    'user_id' => $user->getKey(),
                    'exception' => $exception,
                ]);
            }
        }

        // ASVS 6.3.8: Return the same response whether or not the username exists.
        $message = 'Permintaan ganti password terkirim. Mohon tunggu konfirmasi admin.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('password.request')->with('status', $message);
    }
}
