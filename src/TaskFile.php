<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use DouglasGreen\Exceptions\File;
use DouglasGreen\Exceptions\Regex;
use DouglasGreen\Exceptions\ValueException;

class TaskFile
{
    protected const array HEADERS = [
        'Task name', 'Task URL', 'Recurring?', 'Recur start',
        'Recur end', 'Days of year', 'Days of month', 'Days of week',
        'Times of day', 'Last date reminded',
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
        string $timesOfDayField
    ): void {
        $daysOfYear = $this->splitField($daysOfYearField, '/^(\d\d\d\d-)?\d\d-\d\d$/');

        $daysOfMonth = $this->splitField($daysOfMonthField, '/^([1-9]|[12]\d|3[01])$/');

        $daysOfWeek = $this->splitField($daysOfWeekField, '/^[1-7]$/');

        $timesOfDay = $this->splitField($timesOfDayField, '/^\d\d:\d\d$/');

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
            0
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
        $handle = File::open($this->filename, 'r');
        while (($data = fgetcsv($handle)) !== false) {
            if (! $checkedHeaders) {
                if ($data !== self::HEADERS) {
                    throw new ValueException('Bad headers');
                }

                $checkedHeaders = true;
                continue;
            }

            // Split these fields on load so we can check for error before taking any action.
            $data = array_map('trim', $data);
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
                $lastDateReminded
            ] = $data;

            $recurring = (bool) $recurring;

            $daysOfYear = $this->splitField($daysOfYearField, '/^(\d\d\d\d-)?\d\d-\d\d$/');

            $daysOfMonth = $this->splitField($daysOfMonthField, '/^([1-9]|[12]\d|3[01])$/');

            $daysOfWeek = $this->splitField($daysOfWeekField, '/^[1-7]$/');

            $timesOfDay = $this->splitField($timesOfDayField, '/^\d\d:\d\d$/');

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

        File::close($handle);

        // Sort the tasks by $lastTimeReminded so the oldest is reminded first.
        usort($tasks, static fn($first, $second): int => $first->lastTimeReminded - $second->lastTimeReminded);

        return $tasks;
    }

    /**
     * @param list<Task> $tasks
     * @throws ValueException
     */
    public function saveTasks(array $tasks): void
    {
        $handle = File::open($this->filename, 'w');
        fputcsv($handle, self::HEADERS);
        foreach ($tasks as $task) {
            if (! $task instanceof Task) {
                throw new ValueException('Invalid task provided');
            }

            $recurring = (int) $task->recurring;
            $daysOfYearField = implode('|', $task->daysOfYear);
            $daysOfMonthField = implode('|', $task->daysOfMonth);
            $daysOfWeekField = implode('|', $task->daysOfWeek);
            $timesOfDayField = implode('|', $task->timesOfDay);
            $lastDateReminded = $task->lastTimeReminded > 0 ? date('Y-m-d H:i:s', $task->lastTimeReminded) : '';

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

            fputcsv($handle, $data);
        }

        File::close($handle);
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
     * @return list<string>
     * @throws ValueException
     */
    protected function splitField(string $field, string $regex): array
    {
        $parts = Regex::split('/\s*\|\s*/', $field, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            if ($part === '*') {
                return ['*'];
            }

            if (! Regex::hasMatches($regex, $part)) {
                $error = sprintf('Field "%s" doesn\'t match regex "%s"', $field, $regex);
                throw new ValueException($error);
            }
        }

        return $parts;
    }
}
