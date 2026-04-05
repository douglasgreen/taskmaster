<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Controller;

use PDO;

final class GroupController
{
    public function __construct(private readonly PDO $pdo) {}

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
            $stmt = $this->pdo->prepare("INSERT INTO task_groups (name, created_at) VALUES (?, NOW())");
            $stmt->execute([$name]);
            return (int) $this->pdo->lastInsertId();
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

        $stmt = $this->pdo->prepare("UPDATE task_groups SET name = ? WHERE id = ?");
        $stmt->execute([$name, $group_id]);

        echo json_encode(['success' => true, 'message' => 'Group renamed successfully']);
    }
}
