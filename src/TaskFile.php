<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use DouglasGreen\Exceptions\FileException;
use DouglasGreen\Exceptions\RegexException;
use DouglasGreen\Exceptions\ValueException;

class TaskFile
{
    public const int REMINDER_FIELD = 9;

    /**
     * @param list<string> $headers
     */
    public function __construct(
        protected string $filename,
        protected array $headers
    ) {}

    /**
     * @return list<array{
     *     string,
     *     bool,
     *     bool,
     *     string,
     *     string,
     *     list<string>,
     *     list<string>,
     *     list<string>,
     *     list<string>,
     *     int
     * }>
     * @throws FileException
     * @throws ValueException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function loadTasks(): array
    {
        $tasks = [];
        $taskNames = [];
        $checkedHeaders = false;
        $handle = fopen($this->filename, 'r');
        if ($handle === false) {
            throw new FileException('Unable to open file');
        }

        while (($data = fgetcsv($handle)) !== false) {
            if (! $checkedHeaders) {
                if ($this->headers !== $data) {
                    throw new ValueException('Bad headers');
                }

                $checkedHeaders = true;
                continue;
            }

            // Split these fields on load so we can check for error before taking any action.
            $data = array_map('trim', $data);
            [
                $taskName,
                $done,
                $recurring,
                $recurStart,
                $recurEnd,
                $daysOfYearField,
                $daysOfWeekField,
                $daysOfMonthField,
                $timesOfDayField,
                $lastDateReminded
            ] = $data;
            $taskName = trim((string) preg_replace('/\s+/', ' ', $taskName));
            if (in_array($taskName, $taskNames, true)) {
                throw new ValueException('Duplicate task name: ' . $taskName);
            }

            $taskNames[] = $taskName;
            $done = (bool) $done;
            $recurring = (bool) $recurring;
            if ($recurStart !== '' && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $recurStart) === 0) {
                throw new ValueException('Bad recur start date: ' . $recurStart);
            }

            if ($recurEnd !== '' && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $recurEnd) === 0) {
                throw new ValueException('Bad recur end date: ' . $recurEnd);
            }

            if ($recurEnd !== '' && $recurStart !== '' && $recurEnd < $recurStart) {
                throw new ValueException('Bad recur date range: ' . $recurStart . ' to ' . $recurEnd);
            }

            $daysOfYear = $this->splitField($daysOfYearField, '/^(\d\d\d\d-)?\d\d-\d\d$/');

            $daysOfWeek = $this->splitField($daysOfWeekField, '/^[1-7]$/');

            $daysOfMonth = $this->splitField($daysOfMonthField, '/^([1-9]|[12]\d|3[01])$/');

            $timesOfDay = $this->splitField($timesOfDayField, '/^\d\d:\d\d$/');

            // If there is a time, then there must be a date so use today.
            if ($timesOfDay && $daysOfYear === [] && $daysOfWeek === [] && $daysOfMonth === []) {
                $daysOfYear = [date('Y-m-d')];
            }

            $lastTimeReminded = 0;
            if ($lastDateReminded !== '') {
                $lastTimeReminded = strtotime($lastDateReminded);
                if ($lastTimeReminded === false) {
                    throw new ValueException('Bad last date reminded: ' . $lastDateReminded);
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

        // Sort the tasks by $lastTimeReminded so the oldest is reminded first.
        usort($tasks, static fn($first, $second): int => $first[self::REMINDER_FIELD] - $second[self::REMINDER_FIELD]);

        return $tasks;
    }

    /**
     * @param list<array{
     *     string,
     *     bool,
     *     bool,
     *     string,
     *     string,
     *     list<string>,
     *     list<string>,
     *     list<string>,
     *     list<string>,
     *     int
     * }> $tasks
     * @throws FileException
     */
    public function saveTasks(array $tasks): void
    {
        $handle = fopen($this->filename, 'w');
        if ($handle === false) {
            throw new FileException('Unable to open file for writing');
        }

        fputcsv($handle, $this->headers);
        foreach ($tasks as $task) {
            [
                $taskName,
                $done,
                $recurring,
                $recurStart,
                $recurEnd,
                $daysOfYear,
                $daysOfWeek,
                $daysOfMonth,
                $timesOfDay,
                $lastTimeReminded
            ] = $task;

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

    /**
     * @return list<string>
     * @throws RegexException
     * @throws ValueException
     */
    protected function splitField(string $field, string $regex): array
    {
        $parts = preg_split('/\s*\|\s*/', $field, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new RegexException('Bad regex');
        }

        foreach ($parts as $part) {
            if ($part === '*') {
                return ['*'];
            }

            if (preg_match($regex, $part) === 0) {
                $error = sprintf('Field "%s" doesn\'t match regex "%s"', $field, $regex);
                throw new ValueException($error);
            }
        }

        return $parts;
    }
}
