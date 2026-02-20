<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use Exception;

/**
 * Represents a task with various attributes.
 */
class Task
{
    /**
     * @var array<int, string>
     */
    protected const DAYS_OF_WEEK_NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /**
     * Database ID for this task (set when loaded from database)
     */
    public ?int $dbId = null;

    /**
     * Convert a list of day range expressions into an array of a day or days.
     *
     * @param array<int, string> $dayExpressions
     * @return list<int>|string
     */
    public static function getDayList(array $dayExpressions, int $maxDay): array|string
    {
        $allDays = [];
        foreach ($dayExpressions as $dayOrRange) {
            if ($dayOrRange === '*') {
                return '*';
            }

            $days = explode('-', $dayOrRange);
            if (count($days) === 1) {
                $allDays[] = (int) $dayOrRange;
            } else {
                $allDays = array_merge(
                    $allDays,
                    range((int) $days[0], min((int) $days[1], $maxDay))
                );
            }
        }

        $allDays = array_unique($allDays);
        sort($allDays);
        return $allDays;
    }

    /**
     * @param array<int, string> $daysOfYear
     * @param array<int, string> $daysOfMonth
     * @param array<int, string> $daysOfWeek
     * @param array<int, string> $timesOfDay
     */
    public function __construct(
        public string $taskName,
        public string $taskUrl,
        public ?string $recurStart,
        public ?string $recurEnd,
        public array $daysOfYear,
        public array $daysOfMonth,
        public array $daysOfWeek,
        public array $timesOfDay,
        public int $lastTimeReminded,
    ) {
        $this->taskName = trim((string) preg_replace('/\s+/', ' ', $this->taskName));

        $this->taskUrl = trim($this->taskUrl);

        if ($this->recurStart === '') {
            $this->recurStart = null;
        }

        if ($this->recurEnd === '') {
            $this->recurEnd = null;
        }

        $this->checkRecurDates();

        $dayTypeCount = $this->getDayTypeCount();
        if ($dayTypeCount > 1) {
            $this->error('Only one type of day should be specified');
        }

        // If there is a time, then there must be a date so use today.
        $hasDayType = $dayTypeCount !== 0;
        if ($this->timesOfDay && ! $hasDayType) {
            $this->daysOfYear = [date('Y-m-d')];
            $hasDayType = true;
        }

        if (! $hasDayType) {
            $this->error('Recurring tasks must specify a day');
        }
    }

    /**
     * Represent $daysOfWeek as a list of names (Monday, etc.)
     *
     * @return array<int, string>
     */
    public function getWeekdayNames(): array
    {
        $names = [];
        foreach ($this->daysOfWeek as $dayOfWeek) {
            if ($dayOfWeek === '*') {
                return ['*'];
            }

            if (isset(self::DAYS_OF_WEEK_NAMES[$dayOfWeek])) {
                $names[] = self::DAYS_OF_WEEK_NAMES[$dayOfWeek];
            } else {
                $parts = explode('-', $dayOfWeek);
                $minDay = $parts[0];
                $maxDay = $parts[1] ?? '';
                if (isset(self::DAYS_OF_WEEK_NAMES[$minDay]) && isset(self::DAYS_OF_WEEK_NAMES[$maxDay])) {
                    $names[] = sprintf(
                        '%s to %s',
                        self::DAYS_OF_WEEK_NAMES[$minDay],
                        self::DAYS_OF_WEEK_NAMES[$maxDay]
                    );
                }
            }
        }

        return $names;
    }

    protected function checkRecurDates(): void
    {
        if (
            $this->recurStart !== null &&
            ! preg_match('/^\d\d\d\d-\d\d-\d\d$/', $this->recurStart)
        ) {
            $this->error('Bad recur start date');
        }

        if (
            $this->recurEnd !== null &&
            ! preg_match('/^\d\d\d\d-\d\d-\d\d$/', $this->recurEnd)
        ) {
            $this->error('Bad recur end date');
        }

        if (
            $this->recurEnd !== null &&
            $this->recurStart !== null &&
            $this->recurEnd < $this->recurStart
        ) {
            $this->error('Bad recur date range: ' . $this->recurStart . ' to ' . $this->recurEnd);
        }
    }

    /**
     * @throws Exception
     */
    protected function error(string $message): void
    {
        $fullMessage = $this->taskName . ': ' . $message;
        throw new Exception($fullMessage);
    }

    protected function getDayTypeCount(): int
    {
        $count = 0;
        if ($this->daysOfYear !== []) {
            ++$count;
        }

        if ($this->daysOfMonth !== []) {
            ++$count;
        }

        if ($this->daysOfWeek !== []) {
            ++$count;
        }

        return $count;
    }
}
