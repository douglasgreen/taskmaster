<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Persistence;

use PDO;

/**
 * Repository for task persistence operations.
 */
final readonly class TaskRepository
{
    /**
     * Constructor.
     *
     * @param PDO $pdo The PDO database connection instance.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Find a task by its primary key.
     *
     * @param int $id The task ID.
     *
     * @return array<string, mixed>|null The task data or null if not found.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find all tasks belonging to a group, ordered by due date and title.
     *
     * @param int $groupId The group ID.
     *
     * @return array<array<string, mixed>> The list of tasks.
     */
    public function findByGroupId(int $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE group_id = ? ORDER BY due_date IS NULL, due_date, title');
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Search tasks by term in title or details.
     *
     * @param string $term The search term.
     *
     * @return array<array<string, mixed>> The matching tasks with group names.
     */
    public function search(string $term): array
    {
        $searchTerm = '%' . $term . '%';
        $stmt = $this->pdo->prepare('
            SELECT t.*, tg.name as group_name, tg.id as group_id
            FROM tasks t
            JOIN task_groups tg ON t.group_id = tg.id
            WHERE t.title LIKE ? OR t.details LIKE ?
            ORDER BY tg.name, t.due_date IS NULL, t.due_date, t.title
        ');
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Insert a new task record.
     *
     * @param int $groupId The group the task belongs to.
     * @param string $title The task title.
     * @param string $details Additional details or description.
     * @param string|null $dueDate The due date in 'Y-m-d' format or null.
     *
     * @return int The ID of the newly inserted task.
     */
    public function insert(int $groupId, string $title, string $details, ?string $dueDate): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO tasks (group_id, title, details, due_date, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$groupId, $title, $details, $dueDate]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update a task's properties; optionally move it to a different group.
     *
     * @param int $id The task ID.
     * @param string $title The new title.
     * @param string $details The new details.
     * @param string|null $dueDate The new due date or null.
     * @param int|null $groupId Optional new group ID to move the task.
     *
     * @return int|null The previous group ID if the task was moved; null otherwise or if task not found.
     */
    public function update(int $id, string $title, string $details, ?string $dueDate, ?int $groupId = null): ?int
    {
        if ($groupId !== null) {
            $stmt = $this->pdo->prepare('SELECT group_id FROM tasks WHERE id = ?');
            $stmt->execute([$id]);
            $oldGroupId = $stmt->fetchColumn();
            if ($oldGroupId === false) {
                return null;
            }

            if ($oldGroupId != $groupId) {
                $this->pdo->prepare('UPDATE tasks SET group_id = ? WHERE id = ?')->execute([$groupId, $id]);
                return (int) $oldGroupId;
            }
        }

        $stmt = $this->pdo->prepare('UPDATE tasks SET title = ?, details = ?, due_date = ? WHERE id = ?');
        $stmt->execute([$title, $details, $dueDate, $id]);
        return null;
    }

    /**
     * Delete a task by its ID.
     *
     * @param int $id The task ID.
     *
     * @return int|null The group ID of the deleted task, or null if not found.
     */
    public function delete(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT group_id FROM tasks WHERE id = ?');
        $stmt->execute([$id]);

        $groupId = $stmt->fetchColumn();
        if ($groupId === false) {
            return null;
        }

        $this->pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
        return (int) $groupId;
    }

    /**
     * Move a task to another group.
     *
     * @param int $taskId The task to move.
     * @param int $newGroupId The target group ID.
     *
     * @return int|null The previous group ID, or null if task not found.
     */
    public function move(int $taskId, int $newGroupId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT group_id FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);

        $oldGroupId = $stmt->fetchColumn();
        if ($oldGroupId === false) {
            return null;
        }

        $this->pdo->prepare('UPDATE tasks SET group_id = ? WHERE id = ?')->execute([$newGroupId, $taskId]);
        return (int) $oldGroupId;
    }

    /**
     * Count tasks that are due today or earlier within a group.
     *
     * @param int $groupId The group ID.
     *
     * @return int Number of due tasks.
     */
    public function countDueByGroupId(int $groupId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ? AND due_date IS NOT NULL AND due_date != '0000-00-00' AND due_date <= CURDATE()");
        $stmt->execute([$groupId]);
        return (int) $stmt->fetchColumn();
    }
}
