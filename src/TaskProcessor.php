#!/usr/bin/env php
<?php

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
        protected readonly TaskStorage $taskStorage,
        protected readonly TaskDatabase $taskDatabase,
    ) {
        $this->currentTime = time();
        $this->currentDate = date('Y-m-d', $this->currentTime);
        $this->currentYear = (int) date('Y', $this->currentTime);
        $this->currentDayOfWeek = (int) date('N', $this->currentTime);
        $this->daysInCurrentMonth = (int) date('t', $this->currentTime);
    }

    public function processTasks(): void
    {
        $tasks = $this->taskDatabase->loadTasks();
        $reminderSent = false;

        foreach ($tasks as $task) {
            if (! $this->shouldSendReminder($task)) {
                continue;
            }

            $result = $this->processDates($task);
            $frequency = $result['frequency'];
            $datetimes = $result['datetimes'];

            foreach ($datetimes as $datetime) {
                $scheduledTime = strtotime((string) $datetime);

                $flags = 0;
                if ($frequency === 'daily') {
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

                // Check that scheduled time is past and no reminder has been sent since scheduled time.
                if ($scheduledTime < $this->currentTime && $task->lastTimeReminded < $scheduledTime) {
                    $this->taskStorage->store($task->taskName, $task->taskUrl, $flags);
                    $reminderSent = true;

                    // Set reminder time to current time so it is after scheduled time, marking it as done.
                    $task->lastTimeReminded = $this->currentTime;
                    break;
                }
            }
        }

        if ($reminderSent) {
            $this->taskDatabase->saveTasks($tasks);
        }
    }

    /**
     * @param array<int, string> $dates
     * @param array<int, string> $times
     * @return array<int, string>
     */
    protected static function addTimes(array $dates, array $times): array
    {
        $datetimes = [];

        // There are always dates but there aren't always times. If not specified
        // or specified as '*', time means "start of day".
        if ($times === [] || $times === ['*']) {
            $times[] = '00:00';
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
     * @return array{frequency: ?string, datetimes: array<int, string>}
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
            $daysOfMonth = Task::getDayList($task->daysOfMonth, $this->daysInCurrentMonth);
            if ($daysOfMonth === '*') {
                $frequency = 'daily';
                $dates[] = $this->currentDate;
            } elseif (is_array($daysOfMonth)) {
                $frequency = 'monthly';
                foreach ($daysOfMonth as $dayOfMonth) {
                    $dates[] = date('Y-m') . sprintf('-%02d', $dayOfMonth);
                }
            }

            $datetimes = static::addTimes($dates, $task->timesOfDay);
        } elseif ($task->daysOfWeek !== []) {
            $dates = [];
            $daysOfWeek = Task::getDayList($task->daysOfWeek, 7);
            if ($daysOfWeek === '*') {
                $frequency = 'daily';
                $dates[] = $this->currentDate;
            } elseif (is_array($daysOfWeek)) {
                if ($daysOfWeek === [1, 2, 3, 4, 5]) {
                    $frequency = 'weekdays';
                } elseif ($daysOfWeek === [6, 7]) {
                    $frequency = 'weekends';
                } else {
                    $frequency = 'weekly';
                }

                if (in_array($this->currentDayOfWeek, $daysOfWeek, true)) {
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
        // Check if recurring dates are out of range.
        $recurStartTime = $task->recurStart === null ? null : strtotime($task->recurStart);
        $recurEndTime = $task->recurEnd === null ? null : strtotime($task->recurEnd);

        if ($recurStartTime && $this->currentTime < $recurStartTime) {
            return false;
        }
        return ! ($recurEndTime && $this->currentTime > $recurEndTime);
    }
}
