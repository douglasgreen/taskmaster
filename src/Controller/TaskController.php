<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Controller;

use DouglasGreen\TaskMaster\Persistence\TaskGroupRepository;
use DouglasGreen\TaskMaster\Persistence\TaskRepository;
use InvalidArgumentException;
use Throwable;

final readonly class TaskController
{
    public function __construct(
        private TaskRepository $taskRepo,
        private TaskGroupRepository $groupRepo,
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
                default => throw new InvalidArgumentException('Unknown action: ' . $action),
            };
        } catch (Throwable $throwable) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
        }

        exit;
    }

    private function addTask(): void
    {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        $dueDate = empty($_POST['due_date']) ? null : $_POST['due_date'];

        if ($title === '') {
            throw new InvalidArgumentException('Task title is required');
        }

        $taskId = $this->taskRepo->insert($groupId, $title, $details, $dueDate);
        echo json_encode(['success' => true, 'task_id' => $taskId, 'message' => 'Task added successfully']);
    }

    private function editTask(): void
    {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        $dueDate = empty($_POST['due_date']) ? null : $_POST['due_date'];
        $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : null;

        if ($title === '') {
            throw new InvalidArgumentException('Task title is required');
        }

        $oldGroupId = $this->taskRepo->update($taskId, $title, $details, $dueDate, $groupId);
        if ($oldGroupId !== null) {
            $oldGroupEmpty = $this->groupRepo->deleteIfEmpty($oldGroupId);
            echo json_encode(['success' => true, 'old_group_empty' => $oldGroupEmpty, 'message' => 'Task updated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
        }
    }

    private function deleteTask(): void
    {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $groupId = $this->taskRepo->delete($taskId);

        if ($groupId === null) {
            throw new InvalidArgumentException('Task not found');
        }

        $groupEmpty = $this->groupRepo->deleteIfEmpty($groupId);
        echo json_encode(['success' => true, 'group_empty' => $groupEmpty, 'message' => 'Task deleted successfully']);
    }

    private function moveTask(): void
    {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $newGroupId = (int) ($_POST['new_group_id'] ?? 0);

        $oldGroupId = $this->taskRepo->move($taskId, $newGroupId);
        if ($oldGroupId === null) {
            throw new InvalidArgumentException('Task not found');
        }

        $oldGroupEmpty = $this->groupRepo->deleteIfEmpty($oldGroupId);
        echo json_encode(['success' => true, 'old_group_empty' => $oldGroupEmpty, 'message' => 'Task moved successfully']);
    }

    private function getTask(): void
    {
        $taskId = (int) ($_GET['task_id'] ?? 0);
        $task = $this->taskRepo->findById($taskId);

        if ($task) {
            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
    }
}
