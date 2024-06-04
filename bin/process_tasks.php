#!/usr/bin/env php
<?php

declare(strict_types=1);

use DouglasGreen\TaskMaster\TaskFile;

require_once __DIR__ . '/../vendor/autoload.php';

// @todo Make parameter
date_default_timezone_set('America/New_York');

function sendReminderEmail(string $taskName, bool $isNudge): void
{
    $subject = $isNudge ? 'Nudge: ' : 'Reminder: ';
    $subject .= $taskName;

    // Send the email. For simplicity, we just print a message here.
    echo sprintf('Sending reminder for task: %s%s', $subject, PHP_EOL);
}

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
function processTasks(TaskFile $taskFile): void
{
    $tasks = $taskFile->loadTasks();
    $currentTime = time();
    $currentYear = date('Y', $currentTime);
    $currentDayOfWeek = date('N', $currentTime);
    $currentDate = date('Y-m-d', $currentTime);
    $daysInCurrentMonth = date('t', $currentTime);
    $reminderSent = false;

    // Check if we have already run the program today before sending a nudge.
    $shouldNudge = true;
    foreach ($tasks as $task) {
        $lastTimeReminded = $task[TaskFile::REMINDER_FIELD];
        if (date('Y-m-d', $lastTimeReminded) === $currentDate) {
            $shouldNudge = false;
            break;
        }
    }

    foreach ($tasks as &$task) {
        [$taskName, $done, $recurring, $recurStart, $recurEnd,
            $daysOfYear, $daysOfWeek, $daysOfMonth, $timesOfDay, $lastTimeReminded] = $task;

        // Check whether the task is done.
        if ($done) {
            continue;
        }

        // Don't send more than one reminder per 59 minutes to allow margin for error.
        if ($lastTimeReminded > 0 && $currentTime - $lastTimeReminded < 3540) {
            continue;
        }

        // Don't send more than one reminder on the same date if the time is
        // unspecified otherwise you'd get emails every hour.
        if ($lastTimeReminded > 0 && $timesOfDay === [] && date('Y-m-d', $lastTimeReminded) === $currentDate) {
            continue;
        }

        // Check if recurring dates are out of range.
        if ($recurring) {
            $recurStart = empty($recurStart) ? null : strtotime((string) $recurStart);
            $recurEnd = empty($recurEnd) ? null : strtotime((string) $recurEnd);

            if ($recurStart && $currentTime < $recurStart) {
                continue;
            }

            if ($recurEnd && $currentTime > $recurEnd) {
                continue;
            }
        }

        // Multiply dates and times together into all possible combinations of
        // the chosen date format and the times.
        $datetimes = [];
        if (! empty($daysOfYear)) {
            $dates = [];
            foreach ($daysOfYear as $dayOfYear) {
                if (preg_match('/^\d\d-\d\d$/', (string) $dayOfYear)) {
                    $dayOfYear = $currentYear . '-' . $dayOfYear;
                }

                $dates[] = $dayOfYear === '*' ? $currentDate : $dayOfYear;
            }

            $datetimes = addTimes($dates, $timesOfDay);
        } elseif (! empty($daysOfWeek)) {
            $dates = [];
            foreach ($daysOfWeek as $dayOfWeek) {
                if ($dayOfWeek === '*' || $currentDayOfWeek === $dayOfWeek) {
                    $dates[] = $currentDate;
                }
            }

            $datetimes = addTimes($dates, $timesOfDay);
        } elseif (! empty($daysOfMonth)) {
            $dates = [];
            foreach ($daysOfMonth as $dayOfMonth) {
                if ($dayOfMonth === '*') {
                    $dates[] = $currentDate;
                } elseif ($dayOfMonth <= $daysInCurrentMonth) {
                    $dates[] = date('Y-m') . '-' . str_pad((string) $dayOfMonth, 2, '0', STR_PAD_LEFT);
                } else {
                    $dates[] = date('Y-m') . '-' . $daysInCurrentMonth;
                }
            }

            $datetimes = addTimes($dates, $timesOfDay);
        }

        // If reminder is not recurring and hasn't been sent, send a nudge now.
        $isNudge = false;
        if ($datetimes === [] && ! $recurring && $shouldNudge) {
            $isNudge = true;
            $datetimes[] = date('Y-m-d H:i:s', $currentTime);
        }

        foreach ($datetimes as $datetime) {
            $datetimeSeconds = strtotime((string) $datetime);

            // 14 minutes in seconds
            if (abs($datetimeSeconds - $currentTime) < 840) {
                sendReminderEmail($taskName, $isNudge);
                $reminderSent = true;
                $task[TaskFile::REMINDER_FIELD] = $currentTime;

                // Only send one nudge a day.
                if ($isNudge) {
                    $shouldNudge = false;
                }

                break;
            }
        }
    }

    if ($reminderSent) {
        $taskFile->saveTasks($tasks);
    }
}

/**
 * @param list<string> $dates
 * @param list<string> $times
 * @return list<string>
 */
function addTimes(array $dates, array $times): array
{
    $datetimes = [];

    // There are always dates but there aren't always times. If not specified
    // or specified as '*', time means "right now".
    if ($times === [] || $times === ['*']) {
        $times[] = date('H:i');
    }

    foreach ($dates as $date) {
        foreach ($times as $time) {
            $datetimes[] = $date . ' ' . $time . ':00';
        }
    }

    return $datetimes;
}

// Define the CSV filename and headers
$filename = __DIR__ . '/../assets/data/tasks.csv';
$headers = [
    'Task name', 'Done?', 'Recurring?', 'Recur start', 'Recur end', 'Days of year',
    'Days of week', 'Days of month', 'Times of day', 'Last date reminded',
];
$taskFile = new TaskFile($filename, $headers);
processTasks($taskFile);
