<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Controller;

use PDO;

final class TaskController
{
    public function __construct(private readonly PDO $pdo) {}

    public function handleAjax(string $action): void
    {
        header('Content-Type: application/json');
        try {
            match ($action) {
                'add_task' => $this->addTask(),
                'edit_task' => $this->editTask(),
                'delete_task' => $this->deleteTask(),
                'move_task' => $this->moveTask(),
                'get_task' => $this->getTask(),
                default => throw new \InvalidArgumentException("Unknown action: $action"),
            };
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function addTask(): void
    {
        $group_id = (int) ($_POST['group_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if ($title === '') {
            throw new \InvalidArgumentException('Task title is required');
        }

        $stmt = $this->pdo->prepare("INSERT INTO tasks (group_id, title, details, due_date, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$group_id, $title, $details, $due_date]);
        $task_id = $this->pdo->lastInsertId();

        echo json_encode(['success' => true, 'task_id' => $task_id, 'message' => 'Task added successfully']);
    }

    private function editTask(): void
    {
        $task_id = (int) ($_POST['task_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if ($title === '') {
            throw new \InvalidArgumentException('Task title is required');
        }

        $stmt = $this->pdo->prepare("UPDATE tasks SET title = ?, details = ?, due_date = ? WHERE id = ?");
        $stmt->execute([$title, $details, $due_date, $task_id]);

        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    }

    private function deleteTask(): void
    {
        $task_id = (int) ($_POST['task_id'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT group_id FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $group_id = $stmt->fetchColumn();

        if ($group_id === false) {
            throw new \InvalidArgumentException('Task not found');
        }

        $this->pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$task_id]);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $group_empty = ((int) $stmt->fetchColumn() === 0);

        if ($group_empty) {
            $this->pdo->prepare("DELETE FROM task_groups WHERE id = ?")->execute([$group_id]);
        }

        echo json_encode(['success' => true, 'group_empty' => $group_empty, 'message' => 'Task deleted successfully']);
    }

    private function moveTask(): void
    {
        $task_id = (int) ($_POST['task_id'] ?? 0);
        $new_group_id = (int) ($_POST['new_group_id'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT group_id FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $old_group_id = $stmt->fetchColumn();

        if ($old_group_id === false) {
            throw new \InvalidArgumentException('Task not found');
        }

        $this->pdo->prepare("UPDATE tasks SET group_id = ? WHERE id = ?")->execute([$new_group_id, $task_id]);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ?");
        $stmt->execute([$old_group_id]);
        $old_group_empty = ((int) $stmt->fetchColumn() === 0);

        if ($old_group_empty) {
            $this->pdo->prepare("DELETE FROM task_groups WHERE id = ?")->execute([$old_group_id]);
        }

        echo json_encode(['success' => true, 'old_group_empty' => $old_group_empty, 'message' => 'Task moved successfully']);
    }

    private function getTask(): void
    {
        $task_id = (int) ($_GET['task_id'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
    }
}
