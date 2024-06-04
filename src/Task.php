<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

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
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        public string $taskName,
        public string $taskUrl,
        public bool $done,
        public bool $recurring,
        public ?string $recurStart,
        public ?string $recurEnd,
        public array $daysOfYear,
        public array $daysOfMonth,
        public array $daysOfWeek,
        public array $timesOfDay,
        public int $lastTimeReminded
    ) {}
}
