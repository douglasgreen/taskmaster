<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

class ReminderEmail
{
    public function send(string $taskName, bool $isNudge): void
    {
        $subject = $isNudge ? 'Nudge: ' : 'Reminder: ';
        $subject .= $taskName;

        // Send the email. For simplicity, we just print a message here.
        echo sprintf('Sending reminder for task: %s%s', $subject, PHP_EOL);
    }
}
