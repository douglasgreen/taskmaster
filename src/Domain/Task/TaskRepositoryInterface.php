<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Domain\Task;

interface TaskRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findByGroupId(int $groupId): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term): array;

    public function insert(int $groupId, string $title, string $details, ?string $dueDate): int;

    public function update(int $id, string $title, string $details, ?string $dueDate): void;

    public function delete(int $id): ?int;

    public function move(int $taskId, int $newGroupId): ?int;

    public function countDueByGroupId(int $groupId): int;
}
