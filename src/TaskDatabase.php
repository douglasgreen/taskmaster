<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use Exception;
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
            $recurStart,
            $recurEnd,
            $daysOfYear,
            $daysOfMonth,
            $daysOfWeek,
            $timesOfDay,
            0,
        );

        $daysOfYearStr = $task->daysOfYear ? implode('|', $task->daysOfYear) : null;
        $daysOfMonthStr = $task->daysOfMonth ? implode('|', $task->daysOfMonth) : null;
        $daysOfWeekStr = $task->daysOfWeek ? implode('|', $task->daysOfWeek) : null;
        $timesOfDayStr = $task->timesOfDay ? implode('|', $task->timesOfDay) : null;

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
     * @throws Exception
     */
    public function loadTasks(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, title, details, recur_start, recur_end, days_of_year, days_of_month, days_of_week, time_of_day, last_reminded_at FROM recurring_tasks ORDER BY last_reminded_at ASC, id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $tasks = [];
        foreach ($rows as $row) {
            $daysOfYear = static::splitField(
                $row['days_of_year'] ?? '',
                '/^(\d\d\d\d-)?\d\d-\d\d$/'
            );
            $daysOfMonth = static::splitField(
                $row['days_of_month'] ?? '',
                '/^([1-9]|[12]\d|3[01])$/',
                true
            );
            $daysOfWeek = static::splitField($row['days_of_week'] ?? '', '/^[1-7]$/', true);
            $timesOfDay = static::splitField($row['time_of_day'] ?? '', '/^\d\d:\d\d$/');

            $lastTimeReminded = 0;
            if ($row['last_reminded_at'] !== null) {
                $lastTimeReminded = strtotime($row['last_reminded_at']);
                if ($lastTimeReminded === false) {
                    throw new Exception('Bad last reminded at: ' . $row['last_reminded_at']);
                }
            }

            $task = new Task(
                $row['title'],
                $row['details'] ?? '',
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
            if ($task->dbId === null) {
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

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $tasks = [];
        foreach ($rows as $row) {
            $daysOfYear = static::splitField(
                $row['days_of_year'] ?? '',
                '/^(\d\d\d\d-)?\d\d-\d\d$/'
            );
            $daysOfMonth = static::splitField(
                $row['days_of_month'] ?? '',
                '/^([1-9]|[12]\d|3[01])$/',
                true
            );
            $daysOfWeek = static::splitField($row['days_of_week'] ?? '', '/^[1-7]$/', true);
            $timesOfDay = static::splitField($row['time_of_day'] ?? '', '/^\d\d:\d\d$/');

            $lastTimeReminded = 0;
            if ($row['last_reminded_at'] !== null) {
                $lastTimeReminded = strtotime($row['last_reminded_at']);
                if ($lastTimeReminded === false) {
                    throw new Exception('Bad last reminded at: ' . $row['last_reminded_at']);
                }
            }

            $task = new Task(
                $row['title'],
                $row['details'] ?? '',
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
     * @throws Exception
     */
    protected static function splitField(
        string $field,
        string $regex,
        bool $allowRange = false
    ): array {
        if ($field === '') {
            return [];
        }

        $parts = preg_split('/\s*\|\s*/', $field, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $values = [];
        foreach ($parts as $part) {
            $value = trim($part);

            if ($value === '*') {
                return ['*'];
            }

            if ($allowRange) {
                $rangeValues = preg_split('/\s*-\s*/', $value, 2, PREG_SPLIT_NO_EMPTY);
                if ($rangeValues === false) {
                    $rangeValues = [];
                }

                $count = count($rangeValues);
                if ($count === 1) {
                    self::checkValue($value, $regex);
                } elseif ($count === 2) {
                    self::checkValue($rangeValues[0], $regex);
                    self::checkValue($rangeValues[1], $regex);
                    if ($rangeValues[0] < $rangeValues[1]) {
                        $value = $rangeValues[0] . '-' . $rangeValues[1];
                    } elseif ($rangeValues[0] === $rangeValues[1]) {
                        $value = $rangeValues[0];
                    } else {
                        throw new Exception('Invalid range: ' . $value);
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
     * @throws Exception
     */
    protected static function checkValue(string $value, string $regex): void
    {
        if (! preg_match($regex, $value)) {
            $error = sprintf('Value "%s" doesn\'t match regex "%s"', $value, $regex);
            throw new Exception($error);
        }
    }
}
