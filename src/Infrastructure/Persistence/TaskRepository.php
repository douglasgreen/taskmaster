<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Infrastructure\Persistence;

use DouglasGreen\TaskMaster\Domain\Task\TaskRepositoryInterface;
use PDO;

final readonly class TaskRepository implements TaskRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByGroupId(int $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE group_id = ? ORDER BY due_date IS NULL, due_date, title');
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function search(string $term): array
    {
        $searchTerm = '%' . $term . '%';
        $stmt = $this->pdo->prepare('
            SELECT t.*, tg.name as group_name, tg.id as group_id
            FROM tasks t
            JOIN task_groups tg ON t.group_id = tg.id
            WHERE t.title LIKE ? OR t.details LIKE ?
            ORDER BY tg.name, t.due_date IS NULL, t.due_date, t.title
        ');
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(int $groupId, string $title, string $details, ?string $dueDate): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO tasks (group_id, title, details, due_date, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$groupId, $title, $details, $dueDate]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $title, string $details, ?string $dueDate): void
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET title = ?, details = ?, due_date = ? WHERE id = ?');
        $stmt->execute([$title, $details, $dueDate, $id]);
    }

    public function delete(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT group_id FROM tasks WHERE id = ?');
        $stmt->execute([$id]);

        $groupId = $stmt->fetchColumn();
        if ($groupId === false) {
            return null;
        }

        $this->pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
        return (int) $groupId;
    }

    public function move(int $taskId, int $newGroupId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT group_id FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);

        $oldGroupId = $stmt->fetchColumn();
        if ($oldGroupId === false) {
            return null;
        }

        $this->pdo->prepare('UPDATE tasks SET group_id = ? WHERE id = ?')->execute([$newGroupId, $taskId]);
        return (int) $oldGroupId;
    }

    public function countDueByGroupId(int $groupId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ? AND due_date IS NOT NULL AND due_date != '0000-00-00' AND due_date <= CURDATE()");
        $stmt->execute([$groupId]);
        return (int) $stmt->fetchColumn();
    }
}
