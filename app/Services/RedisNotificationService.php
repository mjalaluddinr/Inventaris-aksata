<?php

namespace App\Services;

use App\Models\Peminjaman;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Predis\Connection\ConnectionException;
use Predis\Connection\Resource\Exception\StreamInitException;
use UnexpectedValueException;

class RedisNotificationService
{
    private const NOTIFICATION_TTL_SECONDS = 86400;

    /**
     * Notify one authenticated recipient.
     */
    public function notifyUser(User $recipient, string $message): void
    {
        $this->storeNotification($recipient, $this->sanitizeNotificationMessage($message));
    }

    /**
     * Notify all active administrators.
     */
    public function notifyAdministrators(string $message): void
    {
        $message = $this->sanitizeNotificationMessage($message);
        $this->administratorRecipients()
            ->where('role', 'admin')
            ->get()
            ->each(fn (User $recipient) => $this->notifyUser($recipient, $message));
    }

    /**
     * Notify administrators and laborans allowed to process loans.
     */
    public function notifyLoanProcessors(string $message): void
    {
        $message = $this->sanitizeNotificationMessage($message);
        $this->administratorRecipients()
            ->where(function (Builder $query): void {
                $query->where('role', 'admin')
                    ->orWhere(function (Builder $laboranQuery): void {
                        $laboranQuery->where('role', 'laboran')
                            ->whereHas(
                                'privileges',
                                static fn (Builder $privilegeQuery) => $privilegeQuery
                                    ->where('hak_akses', 'meminjamkan'),
                            );
                    });
            })
            ->get()
            ->each(fn (User $recipient) => $this->notifyUser($recipient, $message));
    }

    public function unreadCount(User $recipient): int
    {
        return $this->withRecipientLock($recipient, function () use ($recipient): int {
            $redis = Redis::connection('cache');
            $listKey = $this->listKey($recipient);
            $notificationIds = $redis->lrange($listKey, 0, -1);
            $count = 0;

            foreach ($notificationIds as $notificationId) {
                $payload = $redis->get($this->notificationKey($recipient, $notificationId));
                if ($payload === null) {
                    $redis->lrem($listKey, 0, $notificationId);

                    continue;
                }

                try {
                    $notification = $this->decodeNotification($payload);
                    if (! ($notification['read'] ?? false)) {
                        $count++;
                    }
                } catch (UnexpectedValueException) {
                    $redis->lrem($listKey, 0, $notificationId);
                    $redis->del($this->notificationKey($recipient, $notificationId));
                }
            }

            return $count;
        });
    }

    /**
     * @return array<int, array{id: string, message: string, created_at: string, read: bool}>
     */
    public function readAndClear(User $recipient): array
    {
        return $this->withRecipientLock($recipient, function () use ($recipient): array {
            $redis = Redis::connection('cache');
            $listKey = $this->listKey($recipient);
            $notificationIds = $redis->lrange($listKey, 0, -1);
            $notifications = [];
            $displayed = 0;

            foreach ($notificationIds as $notificationId) {
                $notificationKey = $this->notificationKey($recipient, $notificationId);
                $payload = $redis->get($notificationKey);

                if ($payload !== null) {
                    try {
                        $notification = $this->decodeNotification($payload);
                        $notification['message'] = $this->sanitizeNotificationMessage($notification['message']);
                        $notification['read'] = true;
                        $remainingTtl = (int) $redis->ttl($notificationKey);
                        if ($remainingTtl > 0) {
                            $redis->setex(
                                $notificationKey,
                                $remainingTtl,
                                json_encode($notification, JSON_THROW_ON_ERROR),
                            );
                        }

                        if ($displayed < 5) {
                            $notifications[] = $notification;
                            $displayed++;
                        }
                    } catch (UnexpectedValueException) {
                        $redis->lrem($listKey, 0, $notificationId);
                        $redis->del($notificationKey);
                    }
                } else {
                    $redis->lrem($listKey, 0, $notificationId);
                }
            }

            return $notifications;
        });
    }

    /**
     * @return array<int, array{id: string, message: string, created_at: string, read: bool}>
     */
    public function activeNotifications(User $recipient): array
    {
        return $this->withRecipientLock($recipient, function () use ($recipient): array {
            $redis = Redis::connection('cache');
            $listKey = $this->listKey($recipient);
            $notifications = [];

            foreach ($redis->lrange($listKey, 0, -1) as $notificationId) {
                $payload = $redis->get($this->notificationKey($recipient, $notificationId));

                if ($payload === null) {
                    $redis->lrem($listKey, 0, $notificationId);

                    continue;
                }

                try {
                    $notification = $this->decodeNotification($payload);
                    $notification['message'] = $this->sanitizeNotificationMessage($notification['message']);
                    $notifications[] = $notification;
                } catch (UnexpectedValueException) {
                    $redis->lrem($listKey, 0, $notificationId);
                    $redis->del($this->notificationKey($recipient, $notificationId));
                }
            }

            return $notifications;
        });
    }

    public function sendLoanReturnReminders(): int
    {
        $today = now()->startOfDay();
        $sentCount = 0;

        Peminjaman::query()
            ->with('akun')
            ->whereNotNull('tanggal_rencana_kembali')
            ->whereHas('akun', static fn (Builder $query) => $query->where('is_active', true))
            ->whereHas(
                'detailPeminjamans',
                static fn (Builder $query) => $query->where('status', 'dipinjam'),
            )
            ->lazyById(100, column: 'peminjaman_id')
            ->each(function (Peminjaman $loan) use ($today, &$sentCount): void {
                $recipient = $loan->akun;
                $dueDate = $loan->tanggal_rencana_kembali?->copy()->startOfDay();

                if (! $recipient instanceof User || $dueDate === null) {
                    return;
                }

                $daysUntilDue = (int) $today->diffInDays($dueDate, false);
                $reminderStage = match ($daysUntilDue) {
                    7 => '7-days',
                    3 => '3-days',
                    1 => '1-day',
                    default => $daysUntilDue < 0 ? 'overdue' : null,
                };

                if ($reminderStage === null) {
                    return;
                }

                $message = $reminderStage === 'overdue'
                    ? "Pengembalian peminjaman terlambat sejak {$dueDate->format('d-m-Y')}."
                    : "Pengembalian peminjaman jatuh tempo dalam {$daysUntilDue} hari ({$dueDate->format('d-m-Y')}).";

                if ($this->storeNotification(
                    $recipient,
                    $message,
                    "loan-reminder:{$loan->peminjaman_id}:{$reminderStage}",
                )) {
                    $sentCount++;
                }
            });

        return $sentCount;
    }

    public function clearLoanReminderMarkers(string $loanId, string $recipientId): void
    {
        try {
            $redis = Redis::connection('cache');

            foreach (['7-days', '3-days', '1-day', 'overdue'] as $stage) {
                $redis->del($this->deduplicationKey($recipientId, "loan-reminder:{$loanId}:{$stage}"));
            }
        } catch (ConnectionException|LockTimeoutException|StreamInitException $exception) {
            Log::error('Redis loan reminder markers could not be cleared.', [
                'loan_id' => $loanId,
                'exception' => $exception,
            ]);
        }
    }

    private function storeNotification(User $recipient, string $message, ?string $deduplicationKey = null): bool
    {
        $notificationId = (string) Str::uuid();

        try {
            return $this->withRecipientLock($recipient, function () use (
                $recipient,
                $message,
                $deduplicationKey,
                $notificationId,
            ): bool {
                $redis = Redis::connection('cache');
                $markerKey = $deduplicationKey === null
                    ? null
                    : $this->deduplicationKey((string) $recipient->getKey(), $deduplicationKey);

                if ($markerKey !== null && $redis->get($markerKey) !== null) {
                    return false;
                }

                $notification = [
                    'id' => $notificationId,
                    'message' => $message,
                    'created_at' => now()->toIso8601String(),
                    'read' => false,
                ];

                $redis->setex(
                    $this->notificationKey($recipient, $notificationId),
                    self::NOTIFICATION_TTL_SECONDS,
                    json_encode($notification, JSON_THROW_ON_ERROR),
                );
                $redis->lpush($this->listKey($recipient), $notificationId);
                $redis->expire($this->listKey($recipient), self::NOTIFICATION_TTL_SECONDS);

                if ($markerKey !== null) {
                    $redis->setex($markerKey, self::NOTIFICATION_TTL_SECONDS, '1');
                }

                return true;
            });
        } catch (ConnectionException|LockTimeoutException|StreamInitException $exception) {
            Log::error('Redis notification could not be stored.', [
                'recipient_id' => $recipient->getKey(),
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function withRecipientLock(User $recipient, Closure $callback): mixed
    {
        return Cache::store('redis')
            ->lock('notification-lock:'.$recipient->getKey(), 10)
            ->block(2, $callback);
    }

    private function administratorRecipients(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('akun_id');
    }

    private function listKey(User $recipient): string
    {
        return 'inventrack:notifications:'.$recipient->getKey().':unread';
    }

    private function notificationKey(User $recipient, string $notificationId): string
    {
        return 'inventrack:notifications:'.$recipient->getKey().':item:'.$notificationId;
    }

    private function deduplicationKey(string $recipientId, string $eventKey): string
    {
        return 'inventrack:notifications:'.$recipientId.':event:'.hash('sha256', $eventKey);
    }

    public function sanitizeNotificationMessage(string $message): string
    {
        $message = preg_replace('/Pengajuan peminjaman\s+PJM-[0-9]{8}-[0-9]{4,}\b/u', 'Pengajuan peminjaman', $message) ?? $message;
        $message = preg_replace('/Peminjaman\s+PJM-[0-9]{8}-[0-9]{4,}\b/u', 'Peminjaman', $message) ?? $message;
        $message = preg_replace('/\bPJM-[0-9]{8}-[0-9]{4,}\b/u', '', $message) ?? $message;
        $message = preg_replace('/\s{2,}/', ' ', $message) ?? $message;
        $message = preg_replace('/\s+([,.])/u', '$1', $message) ?? $message;
        $message = preg_replace('/\(\s*\)/u', '', $message) ?? $message;

        return trim($message);
    }

    /**
     * @return array{id: string, message: string, created_at: string}
     */
    private function decodeNotification(string $payload): array
    {
        $notification = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        if (
            ! is_array($notification)
            || ! is_string($notification['id'] ?? null)
            || ! is_string($notification['message'] ?? null)
            || ! is_string($notification['created_at'] ?? null)
        ) {
            throw new UnexpectedValueException('Redis contains an invalid notification payload.');
        }

        $notification['read'] = $notification['read'] ?? false;

        return $notification;
    }
}
