<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use DouglasGreen\Utility\Data\ValueException;
use DouglasGreen\Utility\FileSystem\File;
use DouglasGreen\Utility\Regex\Matcher;
use DouglasGreen\Utility\Regex\Regex;

class TaskFile
{
    protected const HEADERS = [
        'Task name',
        'Task URL',
        'Recurring?',
        'Recur start',
        'Recur end',
        'Days of year',
        'Days of month',
        'Days of week',
        'Times of day',
        'Last date reminded',
    ];

    public function __construct(
        protected string $filename
    ) {}

    public function addTask(
        string $taskName,
        string $taskUrl,
        bool $recurring,
        string $recurStart,
        string $recurEnd,
        string $daysOfYearField,
        string $daysOfMonthField,
        string $daysOfWeekField,
        string $timesOfDayField,
    ): void {
        $daysOfYear = static::splitField($daysOfYearField, '/^(\d\d\d\d-)?\d\d-\d\d$/');

        $daysOfMonth = static::splitField($daysOfMonthField, '/^([1-9]|[12]\d|3[01])$/', true);

        $daysOfWeek = static::splitField($daysOfWeekField, '/^[1-7]$/', true);

        $timesOfDay = static::splitField($timesOfDayField, '/^\d\d:\d\d$/');

        $tasks = $this->loadTasks();

        $task = new Task(
            $taskName,
            $taskUrl,
            $recurring,
            $recurStart,
            $recurEnd,
            $daysOfYear,
            $daysOfMonth,
            $daysOfWeek,
            $timesOfDay,
            0,
        );
        $tasks[] = $task;
        $this->saveTasks($tasks);
    }

    /**
     * @return list<Task>
     * @throws ValueException
     */
    public function loadTasks(): array
    {
        $tasks = [];
        $checkedHeaders = false;
        $file = new File($this->filename);
        while (($data = $file->getFields()) !== null) {
            if (! $checkedHeaders) {
                if ($data !== self::HEADERS) {
                    throw new ValueException('Bad headers');
                }

                $checkedHeaders = true;
                continue;
            }

            // Split these fields on load so we can check for error before taking any action.
            $data = array_map(trim(...), $data);
            [
                $taskName,
                $taskUrl,
                $recurring,
                $recurStart,
                $recurEnd,
                $daysOfYearField,
                $daysOfMonthField,
                $daysOfWeekField,
                $timesOfDayField,
                $lastDateReminded,
            ] = $data;

            $recurring = (bool) $recurring;

            $daysOfYear = static::splitField($daysOfYearField, '/^(\d\d\d\d-)?\d\d-\d\d$/');

            $daysOfMonth = static::splitField($daysOfMonthField, '/^([1-9]|[12]\d|3[01])$/', true);

            $daysOfWeek = static::splitField($daysOfWeekField, '/^[1-7]$/', true);

            $timesOfDay = static::splitField($timesOfDayField, '/^\d\d:\d\d$/');

            $lastTimeReminded = 0;
            if ($lastDateReminded !== '') {
                $lastTimeReminded = strtotime($lastDateReminded);
                if ($lastTimeReminded === false) {
                    throw new ValueException('Bad last date reminded: ' . $lastDateReminded);
                }
            }

            $task = new Task(
                $taskName,
                $taskUrl,
                $recurring,
                $recurStart,
                $recurEnd,
                $daysOfYear,
                $daysOfMonth,
                $daysOfWeek,
                $timesOfDay,
                $lastTimeReminded,
            );
            $tasks[] = $task;
        }

        // Sort the tasks by $lastTimeReminded so the oldest is reminded first.
        usort(
            $tasks,
            static fn($first, $second): int => $first->lastTimeReminded - $second->lastTimeReminded,
        );

        return $tasks;
    }

    /**
     * @param list<Task> $tasks
     * @throws ValueException
     */
    public function saveTasks(array $tasks): void
    {
        $file = new File($this->filename, 'w');
        $file->putFields(self::HEADERS);
        foreach ($tasks as $task) {
            if (! $task instanceof Task) {
                throw new ValueException('Invalid task provided');
            }

            $recurring = (int) $task->recurring;
            $daysOfYearField = implode('|', $task->daysOfYear);
            $daysOfMonthField = implode('|', $task->daysOfMonth);
            $daysOfWeekField = implode('|', $task->daysOfWeek);
            $timesOfDayField = implode('|', $task->timesOfDay);
            $lastDateReminded =
                $task->lastTimeReminded > 0 ? date('Y-m-d H:i:s', $task->lastTimeReminded) : '';

            $data = [
                $task->taskName,
                $task->taskUrl,
                $recurring,
                $task->recurStart,
                $task->recurEnd,
                $daysOfYearField,
                $daysOfMonthField,
                $daysOfWeekField,
                $timesOfDayField,
                $lastDateReminded,
            ];

            $file->putFields($data);
        }
    }

    /**
     * Search for a term.
     *
     * @return list<Task>
     */
    public function search(string $term): array
    {
        $tasks = $this->loadTasks();
        $matches = [];
        foreach ($tasks as $task) {
            if (stripos($task->taskName, $term) !== false) {
                $matches[$task->taskName] = $task;
            }
        }

        ksort($matches);
        return array_values($matches);
    }

    /**
     * Check a value against a regexp.
     *
     * @throws ValueException
     */
    protected static function checkValue(string $value, string $regex): void
    {
        if (! Regex::hasMatch($regex, $value)) {
            $error = sprintf('Value "%s" doesn\'t match regex "%s"', $value, $regex);
            throw new ValueException($error);
        }
    }

    /**
     * @return list<string>
     * @throws ValueException
     */
    protected static function splitField(
        string $field,
        string $regex,
        bool $allowRange = false
    ): array {
        $parts = Regex::split('/\s*\|\s*/', $field, -1, Matcher::NO_EMPTY);
        $values = [];
        foreach ($parts as $part) {
            $value = trim($part);

            if ($value === '*') {
                return ['*'];
            }

            if ($allowRange) {
                $rangeValues = Regex::split('/\s*-\s*/', $value, 2, Matcher::NO_EMPTY);
                $count = count($rangeValues);
                if ($count === 1) {
                    self::checkValue($value, $regex);
                } else {
                    self::checkValue($rangeValues[0], $regex);
                    self::checkValue($rangeValues[1], $regex);
                    if ($rangeValues[0] < $rangeValues[1]) {
                        $value = $rangeValues[0] . '-' . $rangeValues[1];
                    } elseif ($rangeValues[0] === $rangeValues[1]) {
                        $value = $rangeValues[0];
                    } else {
                        throw new ValueException('Invalid range: ' . $value);
                    }
                }
            } else {
                self::checkValue($value, $regex);
            }

            $values[] = $value;
        }

        natsort($values);

        return $values;
    }
}
