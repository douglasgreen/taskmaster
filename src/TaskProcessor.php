<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use DouglasGreen\TaskMaster\Domain\RecurringTask\RecurringTaskRepositoryInterface;
use DouglasGreen\TaskMaster\Domain\Task\TaskRepositoryInterface;
use DouglasGreen\TaskMaster\Domain\TaskGroup\TaskGroupRepositoryInterface;
use Exception;

final readonly class TaskProcessor
{
    protected string $currentDate;

    protected int $currentDayOfWeek;

    protected int $currentTime;

    protected int $currentYear;

    protected int $daysInCurrentMonth;

    public function __construct(
        protected RecurringTaskRepositoryInterface $recurringTaskRepo,
        protected TaskRepositoryInterface $taskRepo,
        protected TaskGroupRepositoryInterface $groupRepo,
    ) {
        $this->currentTime = time();
        $this->currentDate = date('Y-m-d', $this->currentTime);
        $this->currentYear = (int) date('Y', $this->currentTime);
        $this->currentDayOfWeek = (int) date('N', $this->currentTime);
        $this->daysInCurrentMonth = (int) date('t', $this->currentTime);
    }

    public function processTasks(): void
    {
        $rows = $this->recurringTaskRepo->findAll();
        $tasks = array_map($this->mapToTask(...), $rows);
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

                if ($scheduledTime < $this->currentTime && $task->lastTimeReminded < $scheduledTime) {
                    $this->storeReminder($task->taskName, $task->taskUrl, $frequency);
                    $reminderSent = true;
                    $task->lastTimeReminded = $this->currentTime;
                    break;
                }
            }
        }

        if ($reminderSent) {
            foreach ($tasks as $task) {
                $lastRemindedAt = $task->lastTimeReminded > 0
                    ? date('Y-m-d H:i:s', $task->lastTimeReminded)
                    : null;
                $this->recurringTaskRepo->updateLastRemindedAt((int) $task->dbId, $lastRemindedAt);
            }
        }
    }

    /**
     * @param array<int, string> $dates
     * @param array<int, string> $times
     *
     * @return array<int, string>
     */
    protected static function addTimes(array $dates, array $times): array
    {
        $datetimes = [];
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
     * @return array<int, string>
     */
    protected static function splitField(string $field, string $regex, bool $allowRange = false): array
    {
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

    protected static function checkValue(string $value, string $regex): void
    {
        if (! preg_match($regex, $value)) {
            throw new Exception(sprintf('Value "%s" doesn\'t match regex "%s"', $value, $regex));
        }
    }

    protected function storeReminder(string $taskName, string $taskUrl, ?Frequency $frequency = null): void
    {
        $title = '';
        if ($frequency instanceof Frequency) {
            $title = $frequency->value . ' ';
        }

        $title .= 'Reminder: ' . $taskName;

        $details = 'Reminder sent by TaskMaster';
        if ($taskUrl !== '') {
            $details .= PHP_EOL . PHP_EOL . 'See ' . $taskUrl;
        }

        $today = date('Y-m-d');

        $group = $this->groupRepo->findByName('Recurring');
        $groupId = $group === null ? $this->groupRepo->insert('Recurring') : (int) $group['id'];

        $this->taskRepo->insert($groupId, $title, $details, $today);
    }

    /** @param array<string, mixed> $row */
    protected function mapToTask(array $row): Task
    {
        $daysOfYear = self::splitField($row['days_of_year'] ?? '', '/^(\d\d\d\d-)?\d\d-\d\d$/');
        $daysOfMonth = self::splitField($row['days_of_month'] ?? '', '/^([1-9]|[12]\d|3[01])$/', true);
        $daysOfWeek = self::splitField($row['days_of_week'] ?? '', '/^[1-7]$/', true);
        $timesOfDay = self::splitField($row['time_of_day'] ?? '', '/^\d\d:\d\d$/');

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
        return $task;
    }

    /**
     * Process dates for a task.
     *
     * @return array{frequency: ?Frequency, datetimes: array<int, string>}
     */
    protected function processDates(Task $task): array
    {
        $datetimes = [];
        $frequency = null;
        if ($task->daysOfYear !== []) {
            $dates = [];
            foreach ($task->daysOfYear as $dayOfYear) {
                if (preg_match('/^\d\d-\d\d$/', (string) $dayOfYear)) {
                    $dayOfYear = $this->currentYear . '-' . $dayOfYear;
                }

                if ($dayOfYear === '*') {
                    $dates[] = $this->currentDate;
                    $frequency = Frequency::Daily;
                } else {
                    $dates[] = $dayOfYear;
                }
            }

            $datetimes = self::addTimes($dates, $task->timesOfDay);
        } elseif ($task->daysOfMonth !== []) {
            $dates = [];
            $daysOfMonth = Task::getDayList($task->daysOfMonth, $this->daysInCurrentMonth);
            if ($daysOfMonth === '*') {
                $frequency = Frequency::Daily;
                $dates[] = $this->currentDate;
            } elseif (is_array($daysOfMonth)) {
                $frequency = Frequency::Monthly;
                foreach ($daysOfMonth as $dayOfMonth) {
                    $dates[] = date('Y-m') . sprintf('-%02d', $dayOfMonth);
                }
            }

            $datetimes = self::addTimes($dates, $task->timesOfDay);
        } elseif ($task->daysOfWeek !== []) {
            $dates = [];
            $daysOfWeek = Task::getDayList($task->daysOfWeek, 7);
            if ($daysOfWeek === '*') {
                $frequency = Frequency::Daily;
                $dates[] = $this->currentDate;
            } elseif (is_array($daysOfWeek)) {
                if ($daysOfWeek === [1, 2, 3, 4, 5]) {
                    $frequency = Frequency::Weekdays;
                } elseif ($daysOfWeek === [6, 7]) {
                    $frequency = Frequency::Weekends;
                } else {
                    $frequency = Frequency::Weekly;
                }

                if (in_array($this->currentDayOfWeek, $daysOfWeek, true)) {
                    $dates[] = $this->currentDate;
                }
            }

            $datetimes = self::addTimes($dates, $task->timesOfDay);
        }

        return ['frequency' => $frequency, 'datetimes' => $datetimes];
    }

    protected function shouldSendReminder(Task $task): bool
    {
        $recurStartTime = $task->recurStart === null ? null : strtotime($task->recurStart);
        $recurEndTime = $task->recurEnd === null ? null : strtotime($task->recurEnd);
        if ($recurStartTime !== null && $this->currentTime < $recurStartTime) {
            return false;
        }

        return ! ($recurEndTime !== null && $this->currentTime > $recurEndTime);
    }
}
