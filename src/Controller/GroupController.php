<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Controller;

use DouglasGreen\TaskMaster\Domain\TaskGroup\TaskGroupRepositoryInterface;

final class GroupController
{
    public function __construct(private readonly TaskGroupRepositoryInterface $groupRepo) {}

    public function handleAjax(string $action): void
    {
        header('Content-Type: application/json');
        try {
            match ($action) {
                'rename_group' => $this->renameGroup(),
                default => throw new \InvalidArgumentException("Unknown action: $action"),
            };
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function handleAddGroup(): ?int
    {
        $name = trim((string) ($_POST['group_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        try {
            return $this->groupRepo->insert($name);
        } catch (\PDOException $e) {
            return null;
        }
    }

    private function renameGroup(): void
    {
        $group_id = (int) ($_POST['group_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('Group name is required');
        }

        $this->groupRepo->updateName($group_id, $name);
        echo json_encode(['success' => true, 'message' => 'Group renamed successfully']);
    }
}
