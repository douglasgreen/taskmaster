<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Domain\TaskGroup;

interface TaskGroupRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function findAll(): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByName(string $name): ?array;

    public function insert(string $name): int;

    public function updateName(int $id, string $name): void;

    public function deleteIfEmpty(int $id): bool;
}
