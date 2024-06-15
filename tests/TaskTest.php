<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Task\Tests;

use DouglasGreen\TaskMaster\Task;
use DouglasGreen\Utility\Data\ValueException;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testInvalidDaysOfYearFormat(): void
    {
        $this->expectException(ValueException::class);
        $this->expectExceptionMessage('Test Task: Non-recurring tasks that specify date must use full YYYY-MM-DD day of year');

        new Task(
            'Test Task',
            'http://example.com',
            false,
            null,
            null,
            ['2024/06/15'],
            [],
            [],
            [],
            0
        );
    }

    public function testInvalidRecurDateRange(): void
    {
        $this->expectException(ValueException::class);
        $this->expectExceptionMessage('Test Task: Bad recur date range: 2024-12-31 to 2024-01-01');

        new Task(
            'Test Task',
            'http://example.com',
            true,
            '2024-12-31',
            '2024-01-01',
            ['2024-06-15'],
            [],
            [],
            ['12:00'],
            0
        );
    }

    public function testNonRecurringTaskWithRecurDates(): void
    {
        $this->expectException(ValueException::class);
        $this->expectExceptionMessage('Test Task: Non-recurring tasks must not specify a recur start or end');

        new Task(
            'Test Task',
            'http://example.com',
            false,
            '2024-01-01',
            '2024-12-31',
            ['2024-06-15'],
            [],
            [],
            ['12:00'],
            0
        );
    }

    public function testValidTaskCreation(): void
    {
        $task = new Task(
            'Test Task',
            'http://example.com',
            true,
            '2024-01-01',
            '2024-12-31',
            ['2024-06-15'],
            [],
            [],
            ['12:00'],
            0
        );

        $this->assertSame('Test Task', $task->taskName);
        $this->assertSame('http://example.com', $task->taskUrl);
        $this->assertTrue($task->recurring);
        $this->assertSame('2024-01-01', $task->recurStart);
        $this->assertSame('2024-12-31', $task->recurEnd);
        $this->assertSame(['2024-06-15'], $task->daysOfYear);
        $this->assertSame([], $task->daysOfMonth);
        $this->assertSame([], $task->daysOfWeek);
        $this->assertSame(['12:00'], $task->timesOfDay);
        $this->assertSame(0, $task->lastTimeReminded);
    }

    public function testTrimTaskName(): void
    {
        $task = new Task(
            '  Test Task  ',
            'http://example.com',
            true,
            '2024-01-01',
            '2024-12-31',
            ['2024-06-15'],
            [],
            [],
            ['12:00'],
            0
        );

        $this->assertSame('Test Task', $task->taskName);
    }
}
