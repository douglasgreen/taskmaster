<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Controller;

use DouglasGreen\TaskMaster\Domain\Task\TaskRepositoryInterface;
use DouglasGreen\TaskMaster\Domain\TaskGroup\TaskGroupRepositoryInterface;

final class TaskController
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepo,
        private readonly TaskGroupRepositoryInterface $groupRepo,
    ) {}

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

        $task_id = $this->taskRepo->insert($group_id, $title, $details, $due_date);
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

        $this->taskRepo->update($task_id, $title, $details, $due_date);
        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    }

    private function deleteTask(): void
    {
        $task_id = (int) ($_POST['task_id'] ?? 0);
        $group_id = $this->taskRepo->delete($task_id);

        if ($group_id === null) {
            throw new \InvalidArgumentException('Task not found');
        }

        $group_empty = $this->groupRepo->deleteIfEmpty($group_id);
        echo json_encode(['success' => true, 'group_empty' => $group_empty, 'message' => 'Task deleted successfully']);
    }

    private function moveTask(): void
    {
        $task_id = (int) ($_POST['task_id'] ?? 0);
        $new_group_id = (int) ($_POST['new_group_id'] ?? 0);

        $old_group_id = $this->taskRepo->move($task_id, $new_group_id);
        if ($old_group_id === null) {
            throw new \InvalidArgumentException('Task not found');
        }

        $old_group_empty = $this->groupRepo->deleteIfEmpty($old_group_id);
        echo json_encode(['success' => true, 'old_group_empty' => $old_group_empty, 'message' => 'Task moved successfully']);
    }

    private function getTask(): void
    {
        $task_id = (int) ($_GET['task_id'] ?? 0);
        $task = $this->taskRepo->findById($task_id);

        if ($task) {
            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
    }
}
