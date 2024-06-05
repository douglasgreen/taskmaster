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
     * @param list<string> $daysOfYear
     * @param list<string> $daysOfMonth
     * @param list<string> $daysOfWeek
     * @param list<string> $timesOfDay
     * @throws ValueException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.NPathComplexity)
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

        if ($this->recurStart !== null && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $this->recurStart) === 0) {
            throw new ValueException('Bad start date');
        }

        if ($this->recurEnd !== null && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $this->recurEnd) === 0) {
            throw new ValueException('Bad end date');
        }

        if ($this->recurEnd !== null && $this->recurStart !== null && $this->recurEnd < $this->recurStart) {
            throw new ValueException('Bad recur date range: ' . $this->recurStart . ' to ' . $this->recurEnd);
        }

        // If there is a time, then there must be a date so use today.
        $emptyDay = $this->daysOfYear === [] && $this->daysOfMonth === [] && $this->daysOfWeek === [];
        if ($this->timesOfDay && $emptyDay) {
            $this->daysOfYear = [date('Y-m-d')];
            $emptyDay = false;
        }

        // If the task is recurring, it must specify a time.
        if (! $this->recurring) {
            return;
        }

        if (! $emptyDay) {
            return;
        }

        throw new ValueException('Recurring tasks must specify a day: "' . $this->taskName . '"');
    }
}
