#!/usr/bin/env php
<?php

declare(strict_types=1);

date_default_timezone_set('America/New_York');

/**
 * @param list<string> $headers
 * @return list<array{string, bool, bool, string, string, list<string>, list<string>, list<string>, list<string>, int}>
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
function loadTasks(string $filename, array $headers): array
{
    $tasks = [];
    $taskNames = [];
    $checkedHeaders = false;
    $handle = fopen($filename, 'r');
    if ($handle === false) {
        throw new Exception('Unable to open file');
    }

    while (($data = fgetcsv($handle)) !== false) {
        if (! $checkedHeaders) {
            if ($headers !== $data) {
                throw new Exception('Bad headers');
            }

            $checkedHeaders = true;
            continue;
        }

        // Split these fields on load so we can check for error before taking any action.
        $data = array_map('trim', $data);
        [$taskName, $done, $recurring, $recurStart, $recurEnd, $daysOfYearField, $daysOfWeekField, $daysOfMonthField, $timesOfDayField, $lastDateReminded] = $data;
        $taskName = trim((string) preg_replace('/\s+/', ' ', $taskName));
        if (in_array($taskName, $taskNames, true)) {
            throw new Exception('Duplicate task name: ' . $taskName);
        }

        $taskNames[] = $taskName;
        $done = (bool) $done;
        $recurring = (bool) $recurring;
        if ($recurStart !== '' && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $recurStart) === 0) {
            throw new Exception('Bad recur start date: ' . $recurStart);
        }

        if ($recurEnd !== '' && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $recurEnd) === 0) {
            throw new Exception('Bad recur end date: ' . $recurEnd);
        }

        if ($recurEnd !== '' && $recurStart !== '' && $recurEnd < $recurStart) {
            throw new Exception('Bad recur date range: ' . $recurStart . ' to ' . $recurEnd);
        }

        $anyDay = ['*'];

        $daysOfYear = $daysOfYearField === '*' ? $anyDay : splitField($daysOfYearField, '/^\d\d-\d\d$/');

        $daysOfWeek = $daysOfWeekField === '*' ? $anyDay : splitField($daysOfWeekField, '/^[1-7]$/');

        $daysOfMonth = $daysOfMonthField === '*' ? $anyDay : splitField($daysOfMonthField, '/^([1-9]|[12]\d|3[01])$/');

        $timesOfDay = splitField($timesOfDayField, '/^\d\d:\d\d$/');

        $lastTimeReminded = 0;
        if ($lastDateReminded !== '') {
            $lastTimeReminded = strtotime($lastDateReminded);
            if ($lastTimeReminded === false) {
                throw new Exception('Bad last date reminded: ' . $lastDateReminded);
            }
        }

        $task = [
            $taskName,
            $done,
            $recurring,
            $recurStart,
            $recurEnd,
            $daysOfYear,
            $daysOfWeek,
            $daysOfMonth,
            $timesOfDay,
            $lastTimeReminded,
        ];
        $tasks[] = $task;
    }

    fclose($handle);

    return $tasks;
}

/**
 * @param list<string> $headers
 * @param list<array{string, bool, bool, string, string, list<string>, list<string>, list<string>, list<string>, int}> $tasks
 */
function saveTasks(string $filename, array $headers, array $tasks): void
{
    $handle = fopen($filename, 'w');
    if ($handle === false) {
        throw new Exception('Unable to open file for writing');
    }

    fputcsv($handle, $headers);
    foreach ($tasks as $task) {
        [$taskName, $done, $recurring, $recurStart, $recurEnd, $daysOfYear, $daysOfWeek, $daysOfMonth, $timesOfDay, $lastTimeReminded] = $task;

        $done = (int) $done;
        $recurring = (int) $recurring;
        $daysOfYearField = implode('|', $daysOfYear);
        $daysOfWeekField = implode('|', $daysOfWeek);
        $daysOfMonthField = implode('|', $daysOfMonth);
        $timesOfDayField = implode('|', $timesOfDay);
        $lastDateReminded = $lastTimeReminded > 0 ? date('Y-m-d H:i:s', $lastTimeReminded) : '';

        $data = [
            $taskName,
            $done,
            $recurring,
            $recurStart,
            $recurEnd,
            $daysOfYearField,
            $daysOfWeekField,
            $daysOfMonthField,
            $timesOfDayField,
            $lastDateReminded,
        ];
        fputcsv($handle, $data);
    }

    fclose($handle);
}

function sendReminderEmail(string $taskName): void
{
    // Send the email. For simplicity, we just print a message here.
    echo sprintf('Sending reminder for task: %s%s', $taskName, PHP_EOL);
}

/**
 * @param list<string> $headers
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
function processTasks(string $filename, array $headers): void
{
    $tasks = loadTasks($filename, $headers);
    $currentTime = time();
    $currentYear = date('Y', $currentTime);
    $currentDayOfWeek = date('N', $currentTime);
    $currentDate = date('Y-m-d', $currentTime);
    $daysInCurrentMonth = date('t', $currentTime);
    $reminderSent = false;
    foreach ($tasks as &$task) {
        [$taskName, $done, $recurring, $recurStart, $recurEnd,
            $daysOfYear, $daysOfWeek, $daysOfMonth, $timesOfDay, $lastTimeReminded] = $task;

        $datetimes = [];
        if (! empty($daysOfYear)) {
            $dates = [];
            foreach ($daysOfYear as $dayOfYear) {
                $dates[] = $dayOfYear === '*' ? $currentDate : $currentYear . '-' . $dayOfYear;
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

        // If reminder is not recurring and hasn't been sent, remind now.
        if ($datetimes === [] && ! $recurring) {
            if ($lastTimeReminded === 0) {
                $useTime = $currentTime;
            } else {
                $projectedTime = strtotime('+30 days', $lastTimeReminded);

                // Allow for last date reminded somehow being in the distant past.
                $useTime = max($currentTime, $projectedTime);
            }

            $datetimes[] = date('Y-m-d H:i:s', $useTime);
        }

        // Check whether the task is done after doing all the error checking.
        if ($done) {
            continue;
        }

        // Don't send more than one reminder on the same date.
        if ($lastTimeReminded > 0 && date('Y-m-d', $lastTimeReminded) === date('Y-m-d')) {
            continue;
        }

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

        foreach ($datetimes as $datetime) {
            $datetimeSeconds = strtotime((string) $datetime);

            // 3 hours in seconds
            if (abs($datetimeSeconds - $currentTime) < 10800) {
                sendReminderEmail($taskName);
                $reminderSent = true;
                $task[9] = $currentTime;
                break;
            }
        }
    }

    if ($reminderSent) {
        saveTasks($filename, $headers, $tasks);
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

    // There are always dates but there aren't always times. If not specified,
    // time means "right now".
    if ($times === []) {
        $times[] = date('H:i');
    }

    foreach ($dates as $date) {
        foreach ($times as $time) {
            $datetimes[] = $date . ' ' . $time . ':00';
        }
    }

    return $datetimes;
}

/**
 * @return list<string>
 */
function splitField(string $field, string $regex): array
{
    $parts = preg_split('/\s*\|\s*/', $field, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        throw new Exception('Bad regex');
    }

    foreach ($parts as $part) {
        if (preg_match($regex, $part) === 0) {
            $error = sprintf('Field "%s" doesn\'t match regex "%s"', $field, $regex);
            throw new Exception($error);
        }
    }

    return $parts;
}

// Define the CSV filename and headers
$filename = __DIR__ . '/../assets/data/tasks.csv';
$headers = [
    'Task name', 'Done?', 'Recurring?', 'Recur start', 'Recur end', 'Days of year',
    'Days of week', 'Days of month', 'Times of day', 'Last date reminded',
];
processTasks($filename, $headers);
