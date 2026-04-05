<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Domain\TaskGroup;

interface TaskGroupRepositoryInterface
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function insert(string $name): int;
    public function updateName(int $id, string $name): void;
    public function deleteIfEmpty(int $id): bool;
}
