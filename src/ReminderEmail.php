<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

class ReminderEmail
{
    public function __construct(
        protected string $email
    ) {}

    public function send(string $taskName, bool $isNudge): void
    {
        $subject = $isNudge ? 'Nudge: ' : 'Reminder: ';
        $subject .= $taskName;

        // Send the email after 1-second delay
        sleep(1);
        mail($this->email, $subject, 'Reminder sent by TaskMaster');
    }
}
