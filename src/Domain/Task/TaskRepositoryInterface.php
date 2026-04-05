<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Domain\Task;

interface TaskRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByGroupId(int $groupId): array;

    public function search(string $term): array;

    public function insert(int $groupId, string $title, string $details, ?string $dueDate): int;

    public function update(int $id, string $title, string $details, ?string $dueDate): void;

    public function delete(int $id): ?int;

    public function move(int $taskId, int $newGroupId): ?int;

    public function countDueByGroupId(int $groupId): int;
}
