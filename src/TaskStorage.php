<?php

namespace DouglasGreen\TaskMaster;

use PDO;
use PDOException;

class TaskStorage
{
    protected PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function store(string $taskName, string $taskUrl, int $flags = 0): void
    {
        $flagChecker = Task::getFlagChecker($flags);
        if ($flagChecker->get('isDaily')) {
            $title = 'Daily ';
        } elseif ($flagChecker->get('isWeekdays')) {
            $title = 'Weekday ';
        } elseif ($flagChecker->get('isWeekends')) {
            $title = 'Weekend ';
        } elseif ($flagChecker->get('isWeekly')) {
            $title = 'Weekly ';
        } elseif ($flagChecker->get('isMonthly')) {
            $title = 'Monthly ';
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
            $stmt = $this->pdo->prepare('INSERT INTO task_groups (name) VALUES (?)');
            $stmt->execute(['Recurring']);
            $groupId = $this->pdo->lastInsertId();
        } else {
            $groupId = $row['id'];
        }

        // Insert the reminder as a task
        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (group_id, title, details, due_date) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$groupId, $title, $details, $today]);
    }
}
