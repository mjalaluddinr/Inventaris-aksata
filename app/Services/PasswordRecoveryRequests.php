<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class PasswordRecoveryRequests
{
    private const REQUESTS_KEY = 'password-recovery:pending-requests';

    private const LOCK_KEY = 'password-recovery:pending-requests:lock';

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $requests = Cache::store('database')->get(self::REQUESTS_KEY, []);

        if (! is_array($requests)) {
            throw new UnexpectedValueException('Password recovery request cache has an invalid shape.');
        }

        return $requests;
    }

    public function submit(?User $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $this->withLock(function (array $requests) use ($user): array {
            $requests[(string) $user->getKey()] = now()->toIso8601String();

            return [$requests, null];
        });
    }

    public function approve(User $user): ?string
    {
        return $this->withLock(function (array $requests) use ($user): array {
            $accountId = (string) $user->getKey();

            if (! array_key_exists($accountId, $requests)) {
                return [$requests, null];
            }

            $lockedUser = User::query()
                ->whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();
            // ASVS 6.4.1 and 11.5.1: Generate a 128-bit CSPRNG secret that is forced to change at first login.
            $temporaryPassword = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

            $lockedUser->update([
                'password_hash' => $temporaryPassword,
                'must_change_password' => true,
            ]);
            unset($requests[$accountId]);

            return [$requests, $temporaryPassword];
        });
    }

    public function reject(User $user): bool
    {
        return $this->withLock(function (array $requests) use ($user): array {
            $accountId = (string) $user->getKey();

            if (! array_key_exists($accountId, $requests)) {
                return [$requests, false];
            }

            unset($requests[$accountId]);

            return [$requests, true];
        });
    }

    private function withLock(callable $callback): mixed
    {
        return Cache::store('database')
            ->lock(self::LOCK_KEY, 10)
            ->block(2, function () use ($callback): mixed {
                return DB::transaction(function () use ($callback): mixed {
                    [$requests, $result] = $callback($this->all());

                    Cache::store('database')->forever(self::REQUESTS_KEY, $requests);

                    return $result;
                });
            });
    }
}
