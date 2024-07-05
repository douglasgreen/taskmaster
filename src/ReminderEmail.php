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
        $flagChecker = Task::getFlagChecker($flags);
        if ($flagChecker->get('isNudge')) {
            $subject = 'Nudge: ';
        } else {
            $subject = '';
            if ($flagChecker->get('isDaily')) {
                $subject = 'Daily ';
            } elseif ($flagChecker->get('isWeekdays')) {
                $subject = 'Weekday ';
            } elseif ($flagChecker->get('isWeekends')) {
                $subject = 'Weekend ';
            } elseif ($flagChecker->get('isWeekly')) {
                $subject = 'Weekly ';
            } elseif ($flagChecker->get('isMonthly')) {
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
