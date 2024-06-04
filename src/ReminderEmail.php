<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

class ReminderEmail
{
    public function __construct(
        protected string $email
    ) {}

    public function send(string $taskName, string $taskUrl, bool $isNudge): void
    {
        $subject = $isNudge ? 'Nudge: ' : 'Reminder: ';
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
