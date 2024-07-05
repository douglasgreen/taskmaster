#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use DouglasGreen\Utility\Regex\Regex;

class TaskProcessor
{
    protected readonly string $currentDate;

    protected readonly int $currentDayOfWeek;

    protected readonly int $currentTime;

    protected readonly int $currentYear;

    protected readonly int $daysInCurrentMonth;

    public function __construct(
        protected readonly ReminderEmail $reminderEmail,
        protected readonly TaskFile $taskFile,
    ) {
        $this->currentTime = time();
        $this->currentDate = date('Y-m-d', $this->currentTime);
        $this->currentYear = (int) date('Y', $this->currentTime);
        $this->currentDayOfWeek = (int) date('N', $this->currentTime);
        $this->daysInCurrentMonth = (int) date('t', $this->currentTime);
    }

    public function processTasks(): void
    {
        $tasks = $this->taskFile->loadTasks();
        $reminderSent = false;

        // Check if we have already run the program today before sending a nudge.
        $shouldNudge = true;
        foreach ($tasks as $task) {
            if (date('Y-m-d', $task->lastTimeReminded) === $this->currentDate) {
                $shouldNudge = false;
                break;
            }
        }

        foreach ($tasks as $task) {
            if (! $this->shouldSendReminder($task)) {
                continue;
            }

            $result = $this->processDates($task);
            $frequency = $result['frequency'];
            $datetimes = $result['datetimes'];

            // If reminder is not recurring and hasn't been sent, send a nudge now.
            $isNudge = false;
            if ($datetimes === [] && ! $task->recurring && $shouldNudge) {
                $isNudge = true;
                $datetimes[] = date('Y-m-d H:i:s', $this->currentTime);
            }

            foreach ($datetimes as $datetime) {
                $datetimeSeconds = strtotime((string) $datetime);

                $flags = 0;
                if ($isNudge) {
                    $flags = Task::IS_NUDGE;
                } elseif ($frequency === 'daily') {
                    $flags = Task::IS_DAILY;
                } elseif ($frequency === 'weekdays') {
                    $flags = Task::IS_WEEKDAYS;
                } elseif ($frequency === 'weekends') {
                    $flags = Task::IS_WEEKENDS;
                } elseif ($frequency === 'weekly') {
                    $flags = Task::IS_WEEKLY;
                } elseif ($frequency === 'monthly') {
                    $flags = Task::IS_MONTHLY;
                }

                // 14 minutes in seconds
                if (abs($datetimeSeconds - $this->currentTime) < 840) {
                    $this->reminderEmail->send($task->taskName, $task->taskUrl, $flags);
                    $reminderSent = true;
                    $task->lastTimeReminded = $this->currentTime;

                    // Only send one nudge a day.
                    if ($isNudge) {
                        $shouldNudge = false;
                    }

                    break;
                }
            }
        }

        if ($reminderSent) {
            $this->taskFile->saveTasks($tasks);
        }
    }

    /**
     * @param list<string> $dates
     * @param list<string> $times
     * @return list<string>
     */
    protected static function addTimes(array $dates, array $times): array
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

    /**
     * Convert a day range expression into an array of a day or days.
     *
     * @return list<int>
     */
    protected static function getRange(string $dayOrRange, int $maxDay): array
    {
        $days = Regex::split('/-/', $dayOrRange, 2, Regex::NO_EMPTY);
        if (count($days) === 1) {
            $days = [$days[0], $days[0]];
        }

        return range((int) $days[0], max((int) $days[1], $maxDay));
    }

    /**
     * Process dates for a task.
     *
     * @return array{frequency: ?string, datetimes: list<string>}
     */
    protected function processDates(Task $task): array
    {
        // Multiply dates and times together into all possible combinations of
        // the chosen date format and the times.
        $datetimes = [];
        $frequency = null;
        if ($task->daysOfYear !== []) {
            $dates = [];
            foreach ($task->daysOfYear as $dayOfYear) {
                if (Regex::hasMatch('/^\d\d-\d\d$/', (string) $dayOfYear)) {
                    $dayOfYear = $this->currentYear . '-' . $dayOfYear;
                }

                if ($dayOfYear === '*') {
                    $dates[] = $this->currentDate;
                    $frequency = 'daily';
                } else {
                    $dates[] = $dayOfYear;
                }
            }

            $datetimes = static::addTimes($dates, $task->timesOfDay);
        } elseif ($task->daysOfMonth !== []) {
            $dates = [];
            foreach ($task->daysOfMonth as $dayOfMonth) {
                if ($dayOfMonth === '*') {
                    $frequency = 'daily';
                    $dates[] = $this->currentDate;
                } else {
                    $range = self::getRange($dayOfMonth, $this->daysInCurrentMonth);
                    $frequency = 'monthly';
                    foreach ($range as $day) {
                        $dates[] = date('Y-m') . sprintf('-%02d', $day);
                    }
                }
            }

            $datetimes = static::addTimes($dates, $task->timesOfDay);
        } elseif ($task->daysOfWeek !== []) {
            $dates = [];
            foreach ($task->daysOfWeek as $dayOfWeek) {
                if ($dayOfWeek === '*') {
                    $frequency = 'daily';
                    $dates[] = $this->currentDate;
                } else {
                    $range = self::getRange($dayOfWeek, 7);
                    if ($range === [1, 2, 3, 4, 5]) {
                        $frequency = 'weekdays';
                    } elseif ($range === [6, 7]) {
                        $frequency = 'weekends';
                    } else {
                        $frequency = 'weekly';
                    }

                    if (in_array($this->currentDayOfWeek, $range, true)) {
                        $dates[] = $this->currentDate;
                    }
                }
            }

            $datetimes = static::addTimes($dates, $task->timesOfDay);
        }

        return [
            'frequency' => $frequency,
            'datetimes' => $datetimes,
        ];
    }

    protected function shouldSendReminder(Task $task): bool
    {
        // Don't send more than one reminder per 59 minutes to allow margin for error.
        if ($task->lastTimeReminded > 0 && $this->currentTime - $task->lastTimeReminded < 3540) {
            return false;
        }

        // Don't send more than one reminder on the same date if the time is
        // unspecified otherwise you'd get emails every hour.
        if (
            $task->lastTimeReminded > 0 &&
            $task->timesOfDay === [] &&
            date('Y-m-d', $task->lastTimeReminded) === $this->currentDate
        ) {
            return false;
        }

        // Check if recurring dates are out of range.
        if ($task->recurring) {
            $recurStartTime = $task->recurStart === null ? null : strtotime($task->recurStart);
            $recurEndTime = $task->recurEnd === null ? null : strtotime($task->recurEnd);

            if ($recurStartTime && $this->currentTime < $recurStartTime) {
                return false;
            }

            if ($recurEndTime && $this->currentTime > $recurEndTime) {
                return false;
            }
        }

        return true;
    }
}
