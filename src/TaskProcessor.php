#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use DouglasGreen\Utility\Regex\Regex;

class TaskProcessor
{
    protected readonly string $currentDate;

    protected readonly string $currentDayOfWeek;

    protected readonly string $currentYear;

    protected readonly string $daysInCurrentMonth;

    protected readonly int $currentTime;

    public function __construct(
        protected readonly ReminderEmail $reminderEmail,
        protected readonly TaskFile $taskFile,
    ) {
        $this->currentTime = time();
        $this->currentYear = date('Y', $this->currentTime);
        $this->currentDayOfWeek = date('N', $this->currentTime);
        $this->currentDate = date('Y-m-d', $this->currentTime);
        $this->daysInCurrentMonth = date('t', $this->currentTime);
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
                } elseif ($dayOfMonth <= $this->daysInCurrentMonth) {
                    $frequency = 'monthly';
                    $dates[] =
                        date('Y-m') . '-' . str_pad((string) $dayOfMonth, 2, '0', STR_PAD_LEFT);
                } else {
                    $dates[] = date('Y-m') . '-' . $this->daysInCurrentMonth;
                }
            }

            $datetimes = static::addTimes($dates, $task->timesOfDay);
        } elseif ($task->daysOfWeek !== []) {
            $dates = [];
            foreach ($task->daysOfWeek as $dayOfWeek) {
                if ($dayOfWeek === '*') {
                    $frequency = 'daily';
                    $dates[] = $this->currentDate;
                } elseif ($this->currentDayOfWeek === $dayOfWeek) {
                    $frequency = 'weekly';
                    $dates[] = $this->currentDate;
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
