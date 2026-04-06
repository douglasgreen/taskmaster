<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Infrastructure\Persistence;

use DouglasGreen\TaskMaster\Domain\RecurringTask\RecurringTaskRepositoryInterface;
use PDO;

final readonly class RecurringTaskRepository implements RecurringTaskRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recurring_tasks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM recurring_tasks ORDER BY title ASC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function search(string $term): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recurring_tasks WHERE title LIKE ? ORDER BY title ASC');
        $stmt->execute(['%' . $term . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(
        string $title,
        string $details,
        ?string $recurStart,
        ?string $recurEnd,
        ?string $daysOfYear,
        ?string $daysOfMonth,
        ?string $daysOfWeek,
        ?string $timeOfDay,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recurring_tasks (title, details, recur_start, recur_end, days_of_year, days_of_month, days_of_week, time_of_day, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        );
        $stmt->execute([$title, $details, $recurStart, $recurEnd, $daysOfYear, $daysOfMonth, $daysOfWeek, $timeOfDay]);
        return (int) $this->pdo->lastInsertId();
    }

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
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE recurring_tasks SET title = ?, details = ?, recur_start = ?, recur_end = ?, days_of_year = ?, days_of_month = ?, days_of_week = ?, time_of_day = ? WHERE id = ?',
        );
        $stmt->execute([$title, $details, $recurStart, $recurEnd, $daysOfYear, $daysOfMonth, $daysOfWeek, $timeOfDay, $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM recurring_tasks WHERE id = ?')->execute([$id]);
    }

    public function updateLastRemindedAt(int $id, ?string $lastRemindedAt): void
    {
        $this->pdo->prepare('UPDATE recurring_tasks SET last_reminded_at = ? WHERE id = ?')->execute([$lastRemindedAt, $id]);
    }
}
