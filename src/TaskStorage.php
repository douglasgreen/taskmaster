<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

use PDO;

class TaskStorage
{
    public function __construct(
        protected PDO $pdo
    ) {}

    public function store(string $taskName, string $taskUrl, ?Frequency $frequency = null): void
    {
        $title = '';
        if ($frequency instanceof Frequency) {
            $title = $frequency->value . ' ';
        }

        $title .= 'Reminder: ';

        $title .= $taskName;

        $details = 'Reminder sent by TaskMaster';
        if ($taskUrl !== '') {
            $details .= PHP_EOL . PHP_EOL . 'See ' . $taskUrl;
        }

        $today = date('Y-m-d');

        // Ensure "Recurring" group exists
        $stmt = $this->pdo->prepare('SELECT id FROM task_groups WHERE name = ?');
        $stmt->execute(['Recurring']);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            $stmt = $this->pdo->prepare('INSERT INTO task_groups (name, created_at) VALUES (?, NOW())');
            $stmt->execute(['Recurring']);
            $groupId = $this->pdo->lastInsertId();
        } else {
            $groupId = $row['id'];
        }

        // Insert the reminder as a task
        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (group_id, title, details, due_date, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$groupId, $title, $details, $today]);
    }
}
