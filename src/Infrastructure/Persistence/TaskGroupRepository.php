<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Infrastructure\Persistence;

use DouglasGreen\TaskMaster\Domain\TaskGroup\TaskGroupRepositoryInterface;
use PDO;

final readonly class TaskGroupRepository implements TaskGroupRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM task_groups ORDER BY name');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM task_groups WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM task_groups WHERE name = ?');
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(string $name): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO task_groups (name, created_at) VALUES (?, NOW())');
        $stmt->execute([$name]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateName(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare('UPDATE task_groups SET name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
    }

    public function deleteIfEmpty(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tasks WHERE group_id = ?');
        $stmt->execute([$id]);

        $count = (int) $stmt->fetchColumn();
        if ($count === 0) {
            $this->pdo->prepare('DELETE FROM task_groups WHERE id = ?')->execute([$id]);
            return true;
        }

        return false;
    }
}
