<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use DouglasGreen\Exceptions\ValueException;

/**
 * Represents a task with various attributes.
 */
class Task
{
    /**
     * @var array<int, string>
     */
    protected const array DAYS_OF_WEEK_NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /**
     * @param list<string> $daysOfYear
     * @param list<string> $daysOfMonth
     * @param list<string> $daysOfWeek
     * @param list<string> $timesOfDay
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        public string $taskName,
        public string $taskUrl,
        public bool $recurring,
        public ?string $recurStart,
        public ?string $recurEnd,
        public array $daysOfYear,
        public array $daysOfMonth,
        public array $daysOfWeek,
        public array $timesOfDay,
        public int $lastTimeReminded
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

        if (! $this->recurring) {
            if ($hasDayType) {
                $this->checkNonRecurDates();
            }

            return;
        }

        if (! $hasDayType) {
            $this->error('Recurring tasks must specify a day');
        }
    }

    /**
     * Represent $daysOfWeek as a list of names (Monday, etc.)
     *
     * @return list<string>
     */
    public function getWeekdayNames(): array
    {
        $names = [];
        foreach ($this->daysOfWeek as $dayOfWeek) {
            if (isset(self::DAYS_OF_WEEK_NAMES[$dayOfWeek]) || $dayOfWeek === '*') {
                $names[] = self::DAYS_OF_WEEK_NAMES[$dayOfWeek];
            }
        }

        return $names;
    }

    protected function checkNonRecurDates(): void
    {
        $error = 'Non-recurring tasks that specify date must use full YYYY-MM-DD day of year';
        if ($this->daysOfYear === []) {
            $this->error($error);
        }

        foreach ($this->daysOfYear as $dayOfYear) {
            if (preg_match('/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/', $dayOfYear) === 0) {
                $this->error($error);
            }
        }
    }

    protected function checkRecurDates(): void
    {
        if ($this->recurStart !== null && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $this->recurStart) === 0) {
            $this->error('Bad recur start date');
        }

        if ($this->recurEnd !== null && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $this->recurEnd) === 0) {
            $this->error('Bad recur end date');
        }

        if ($this->recurEnd !== null && $this->recurStart !== null && $this->recurEnd < $this->recurStart) {
            $this->error('Bad recur date range: ' . $this->recurStart . ' to ' . $this->recurEnd);
        }

        if ($this->recurring) {
            return;
        }

        if ($this->recurEnd === null && $this->recurStart === null) {
            return;
        }

        $this->error('Non-recurring tasks must not specify a recur start or end');
    }

    /**
     * @throws ValueException
     */
    protected function error(string $message): void
    {
        $fullMessage = $this->taskName . ': ' . $message;
        throw new ValueException($fullMessage);
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
