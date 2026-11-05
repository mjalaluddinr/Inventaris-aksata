<?php

namespace App\Console\Commands;

use App\Services\RedisNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-loan-return-reminders')]
#[Description('Send Redis notifications for upcoming and overdue loan returns')]
class SendLoanReturnReminders extends Command
{
    public function handle(RedisNotificationService $notifications): int
    {
        $sentCount = $notifications->sendLoanReturnReminders();

        $this->info("{$sentCount} loan return reminder(s) sent.");

        return self::SUCCESS;
    }
}
