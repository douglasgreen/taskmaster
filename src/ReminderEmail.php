<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

class ReminderEmail
{
    public function __construct(
        protected string $email
    ) {}

    public function send(string $taskName, string $taskUrl, int $flags = 0): void
    {
        $isNudge = (bool) ($flags & Task::IS_NUDGE);
        $isDaily = (bool) ($flags & Task::IS_DAILY);
        $isWeekly = (bool) ($flags & Task::IS_WEEKLY);
        $isMonthly = (bool) ($flags & Task::IS_MONTHLY);

        if ($isNudge) {
            $subject = 'Nudge: ';
        } else {
            $subject = '';
            if ($isDaily) {
                $subject = 'Daily ';
            } elseif ($isWeekly) {
                $subject = 'Weekly ';
            } elseif ($isMonthly) {
                $subject = 'Monthly ';
            }

            $subject .= 'Reminder: ';
        }

        $subject .= $taskName;

        $body = 'Reminder sent by TaskMaster';
        if ($taskUrl !== '') {
            $body .= PHP_EOL . PHP_EOL . 'See ' . $taskUrl;
        }

        // Send the email after 1-second delay
        sleep(1);
        mail($this->email, $subject, $body);
    }
}
