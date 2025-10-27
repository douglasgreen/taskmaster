<?php

namespace DouglasGreen\TaskMaster;

use DouglasGreen\Utility\Data\ValueException;
use DouglasGreen\Utility\Regex\Matcher;
use DouglasGreen\Utility\Regex\Regex;
use PDO;

class TaskDatabase
{
    public function __construct(
        protected readonly PDO $pdo
    ) {}

    /**
     * Add a new task to the database.
     */
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

        $daysOfYearStr = implode('|', $task->daysOfYear);
        $daysOfMonthStr = implode('|', $task->daysOfMonth);
        $daysOfWeekStr = implode('|', $task->daysOfWeek);
        $timesOfDayStr = implode('|', $task->timesOfDay);

        $stmt = $this->pdo->prepare(
            'INSERT INTO recurring_tasks (title, details, recur_start, recur_end, days_of_year, days_of_month, days_of_week, time_of_day, last_reminded_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())'
        );
        $stmt->execute([
            $task->taskName,
            $task->taskUrl,
            $task->recurStart,
            $task->recurEnd,
            $daysOfYearStr,
            $daysOfMonthStr,
            $daysOfWeekStr,
            $timesOfDayStr,
        ]);
    }

    /**
     * Load all recurring tasks from the database.
     *
     * @return list<Task>
     * @throws ValueException
     */
    public function loadTasks(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, title, details, recur_start, recur_end, days_of_year, days_of_month, days_of_week, time_of_day, last_reminded_at FROM recurring_tasks ORDER BY last_reminded_at ASC, id ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tasks = [];
        foreach ($rows as $row) {
            $daysOfYear = static::splitField($row['days_of_year'] ?? '', '/^(\d\d\d\d-)?\d\d-\d\d$/');
            $daysOfMonth = static::splitField($row['days_of_month'] ?? '', '/^([1-9]|[12]\d|3[01])$/', true);
            $daysOfWeek = static::splitField($row['days_of_week'] ?? '', '/^[1-7]$/', true);
            $timesOfDay = static::splitField($row['time_of_day'] ?? '', '/^\d\d:\d\d$/');

            $lastTimeReminded = 0;
            if ($row['last_reminded_at'] !== null) {
                $lastTimeReminded = strtotime($row['last_reminded_at']);
                if ($lastTimeReminded === false) {
                    throw new ValueException('Bad last reminded at: ' . $row['last_reminded_at']);
                }
            }

            $task = new Task(
                $row['title'],
                $row['details'] ?? '',
                true, // All tasks in this table are recurring
                $row['recur_start'],
                $row['recur_end'],
                $daysOfYear,
                $daysOfMonth,
                $daysOfWeek,
                $timesOfDay,
                $lastTimeReminded,
            );

            // Store the database ID for later updates
            $task->dbId = (int) $row['id'];

            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * Save updated task data back to the database.
     *
     * @param list<Task> $tasks
     */
    public function saveTasks(array $tasks): void
    {
        foreach ($tasks as $task) {
            if (!isset($task->dbId)) {
                continue;
            }

            $lastRemindedAt = $task->lastTimeReminded > 0
                ? date('Y-m-d H:i:s', $task->lastTimeReminded)
                : null;

            $stmt = $this->pdo->prepare(
                'UPDATE recurring_tasks SET last_reminded_at = ? WHERE id = ?'
            );
            $stmt->execute([$lastRemindedAt, $task->dbId]);
        }
    }

    /**
     * Search for tasks by term.
     *
     * @return list<Task>
     */
    public function search(string $term): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, details, recur_start, recur_end, days_of_year, days_of_month, days_of_week, time_of_day, last_reminded_at FROM recurring_tasks WHERE title LIKE ? ORDER BY title ASC'
        );
        $stmt->execute(['%' . $term . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tasks = [];
        foreach ($rows as $row) {
            $daysOfYear = static::splitField($row['days_of_year'] ?? '', '/^(\d\d\d\d-)?\d\d-\d\d$/');
            $daysOfMonth = static::splitField($row['days_of_month'] ?? '', '/^([1-9]|[12]\d|3[01])$/', true);
            $daysOfWeek = static::splitField($row['days_of_week'] ?? '', '/^[1-7]$/', true);
            $timesOfDay = static::splitField($row['time_of_day'] ?? '', '/^\d\d:\d\d$/');

            $lastTimeReminded = 0;
            if ($row['last_reminded_at'] !== null) {
                $lastTimeReminded = strtotime($row['last_reminded_at']);
                if ($lastTimeReminded === false) {
                    throw new ValueException('Bad last reminded at: ' . $row['last_reminded_at']);
                }
            }

            $task = new Task(
                $row['title'],
                $row['details'] ?? '',
                true,
                $row['recur_start'],
                $row['recur_end'],
                $daysOfYear,
                $daysOfMonth,
                $daysOfWeek,
                $timesOfDay,
                $lastTimeReminded,
            );

            $task->dbId = (int) $row['id'];
            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * @return array<int, string>
     * @throws ValueException
     */
    protected static function splitField(
        string $field,
        string $regex,
        bool $allowRange = false
    ): array {
        if ($field === '') {
            return [];
        }

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

    /**
     * @throws ValueException
     */
    protected static function checkValue(string $value, string $regex): void
    {
        if (!Regex::hasMatch($regex, $value)) {
            $error = sprintf('Value "%s" doesn\'t match regex "%s"', $value, $regex);
            throw new ValueException($error);
        }
    }
}
