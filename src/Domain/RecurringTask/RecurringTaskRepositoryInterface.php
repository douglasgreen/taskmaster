<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Domain\RecurringTask;

interface RecurringTaskRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return list<array<string, mixed>> */
    public function findAll(): array;

    /** @return list<array<string, mixed>> */
    public function search(string $term): array;

    public function insert(
        string $title,
        string $details,
        ?string $recurStart,
        ?string $recurEnd,
        ?string $daysOfYear,
        ?string $daysOfMonth,
        ?string $daysOfWeek,
        ?string $timeOfDay,
    ): int;

    public function update(
        int $id,
        string $title,
        string $details,
        ?string $recurStart,
        ?string $recurEnd,
        ?string $daysOfYear,
        ?string $daysOfMonth,
        ?string $daysOfWeek,
        ?string $timeOfDay,
    ): void;

    public function delete(int $id): void;

    public function updateLastRemindedAt(int $id, ?string $lastRemindedAt): void;
}
