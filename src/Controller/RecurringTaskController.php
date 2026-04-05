<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Controller;

use PDO;

final class RecurringTaskController
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
        $name = trim((string) ($_POST['name'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $start = !empty($_POST['recur_start']) ? $_POST['recur_start'] : '';
        $end = !empty($_POST['recur_end']) ? $_POST['recur_end'] : '';
        $time = trim((string) ($_POST['time_of_day'] ?? ''));
        $freq = $_POST['frequency_type'] ?? '';

        $daysOfWeek = '';
        $daysOfMonth = '';
        $daysOfYear = '';

        if ($freq === 'weekly' && isset($_POST['days_of_week']) && is_array($_POST['days_of_week'])) {
            $daysOfWeek = implode('|', $_POST['days_of_week']);
        } elseif ($freq === 'monthly') {
            $daysOfMonth = trim((string) ($_POST['days_of_month'] ?? ''));
        } elseif ($freq === 'yearly') {
            $daysOfYear = trim((string) ($_POST['days_of_year'] ?? ''));
        }

        if ($name === '') {
            throw new \InvalidArgumentException('Task name is required');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO recurring_tasks (title, details, recur_start, recur_end, days_of_year, days_of_month, days_of_week, time_of_day, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$name, $url, $start, $end, $daysOfYear, $daysOfMonth, $daysOfWeek, $time]);

        echo json_encode(['success' => true, 'message' => 'Recurring task added successfully']);
    }

    private function editTask(): void
    {
        $id = (int) ($_POST['task_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $details = trim((string) ($_POST['url'] ?? ''));
        $start = !empty($_POST['recur_start']) ? $_POST['recur_start'] : null;
        $end = !empty($_POST['recur_end']) ? $_POST['recur_end'] : null;
        $time = trim((string) ($_POST['time_of_day'] ?? ''));
        $freq = $_POST['frequency_type'] ?? '';

        $daysOfWeek = null;
        $daysOfMonth = null;
        $daysOfYear = null;

        if ($freq === 'weekly' && isset($_POST['days_of_week']) && is_array($_POST['days_of_week'])) {
            $daysOfWeek = implode('|', $_POST['days_of_week']);
        } elseif ($freq === 'monthly') {
            $daysOfMonth = trim((string) ($_POST['days_of_month'] ?? '')) ?: null;
        } elseif ($freq === 'yearly') {
            $daysOfYear = trim((string) ($_POST['days_of_year'] ?? '')) ?: null;
        }

        if ($name === '') {
            throw new \InvalidArgumentException('Name is required');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE recurring_tasks SET title = ?, details = ?, recur_start = ?, recur_end = ?, days_of_week = ?, days_of_month = ?, days_of_year = ?, time_of_day = ? WHERE id = ?"
        );
        $stmt->execute([$name, $details, $start, $end, $daysOfWeek, $daysOfMonth, $daysOfYear, $time, $id]);

        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    }

    private function deleteTask(): void
    {
        $id = (int) ($_POST['task_id'] ?? 0);
        $this->pdo->prepare("DELETE FROM recurring_tasks WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
    }

    private function getTask(): void
    {
        $id = (int) ($_GET['task_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM recurring_tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            $freq = 'daily';
            if (!empty($task['days_of_week'])) { $freq = 'weekly'; }
            if (!empty($task['days_of_month'])) { $freq = 'monthly'; }
            if (!empty($task['days_of_year'])) { $freq = 'yearly'; }

            $weekArr = [];
            if ($task['days_of_week']) {
                $parts = explode('|', (string) $task['days_of_week']);
                foreach ($parts as $p) {
                    if (str_contains($p, '-')) {
                        [$s, $e] = explode('-', $p);
                        for ($i = (int)$s; $i <= (int)$e; $i++) {
                            $weekArr[] = $i;
                        }
                    } else {
                        $weekArr[] = $p;
                    }
                }
            }
            $task['days_of_week_arr'] = $weekArr;
            $task['frequency_type'] = $freq;
            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
    }
}
