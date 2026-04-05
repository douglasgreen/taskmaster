<?php

declare(strict_types=1);

use DouglasGreen\TaskMaster\Controller\GroupController;
use DouglasGreen\TaskMaster\Controller\TaskController;

['pdo' => $pdo, 'twig' => $twig] = require __DIR__ . '/../bootstrap.php';

$taskController = new TaskController($pdo);
$groupController = new GroupController($pdo);

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    $action = $_GET['ajax'];
    if (in_array($action, ['add_task', 'edit_task', 'delete_task', 'move_task', 'get_task'], true)) {
        $taskController->handleAjax($action);
    } elseif ($action === 'rename_group') {
        $groupController->handleAjax($action);
    } else {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}

// Handle traditional POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group'])) {
    $new_group_id = $groupController->handleAddGroup();
    if ($new_group_id !== null) {
        header(sprintf('Location: ?group=%s&msg=group_added', $new_group_id));
        exit;
    } else {
        $error_message = 'Error adding group.';
    }
}

// Simple Front Controller Routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$page = $_GET['page'] ?? match ($uri) {
    '/recurring.php', '/recurring' => 'recurring',
    default => 'tasks',
};

match ($page) {
    'recurring' => require __DIR__ . '/pages/recurring.php',
    default => require __DIR__ . '/pages/tasks.php',
};

// Helper Functions
function formatDueDate(?string $due_date_str): string {
    if (empty($due_date_str) || $due_date_str === '0000-00-00') {
        return '';
    }

    $due = new DateTime($due_date_str);
    $now = new DateTime();
    $now->setTime(0, 0, 0);
    $due->setTime(0, 0, 0);
    $interval = $now->diff($due);
    $days_from_now = (int) ($interval->invert ? -$interval->days : $interval->days);
    $abs_days = abs($days_from_now);

    if ($abs_days > 99) {
        return $due_date_str;
    }
    if ($days_from_now < 0) {
        if ($abs_days == 1) {
            return 'yesterday';
        }
        return $abs_days . ' days ago';
    }
    if ($days_from_now === 0) {
        return 'today';
    }
    if ($days_from_now === 1) {
        return 'tomorrow';
    }
    return 'in ' . $days_from_now . ' days';
}

function formatDetails(mixed $details): string {
    if (empty($details)) {
        return '';
    }
    preg_match_all('/(https?:\/\/\S+)/', $details, $matches, PREG_SET_ORDER);
    $placeholders = [];
    $counter = 0;
    $processed = $details;
    foreach ($matches as $match) {
        $url = $match[1];
        $placeholder = '__URL_' . $counter . '__';
        $placeholders[$placeholder] = $url;
        $processed = str_replace($url, $placeholder, $processed);
        $counter++;
    }
    $escaped = nl2br(htmlspecialchars((string) $processed, ENT_QUOTES, 'UTF-8'));
    foreach ($placeholders as $ph => $url) {
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? '';
        if ($domain) {
            $link_html = '<a target="_blank" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') . '</a>';
        } else {
            $link_html = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
        $escaped = str_replace($ph, $link_html, $escaped);
    }
    return $escaped;
}

function isTaskDue(?string $due_date_str): bool {
    if (empty($due_date_str) || $due_date_str === '0000-00-00') {
        return false;
    }
    $due = new DateTime($due_date_str);
    $now = new DateTime();
    $due->setTime(0, 0, 0);
    $now->setTime(0, 0, 0);
    return $due <= $now;
}

function getDueCount(PDO $pdo, int $group_id): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ? AND due_date IS NOT NULL AND due_date != '0000-00-00' AND due_date <= CURDATE()");
    $stmt->execute([$group_id]);
    return (int)$stmt->fetchColumn();
}

$selected_group = $_GET['group'] ?? null;
$search_query = $_GET['search'] ?? '';
$message = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'group_added') {
    $message = 'Group added successfully.';
}

// Validate selected group exists
if ($selected_group) {
    $stmt = $pdo->prepare("SELECT id FROM task_groups WHERE id = ?");
    $stmt->execute([$selected_group]);
    if (!$stmt->fetch()) {
        $selected_group = null;
    }
}

// Fetch groups with due counts
$stmt = $pdo->query("SELECT * FROM task_groups ORDER BY name");
$groups = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
foreach ($groups as &$group) {
    $group['due_count'] = getDueCount($pdo, $group['id']);
}
unset($group);

// Fetch tasks for selected group or search results
$tasks = [];
$search_results_by_group = [];
$is_searching = !empty($search_query);

if ($is_searching) {
    $search_term = '%' . $search_query . '%';
    $stmt = $pdo->prepare("
        SELECT t.*, tg.name as group_name, tg.id as group_id
        FROM tasks t
        JOIN task_groups tg ON t.group_id = tg.id
        WHERE t.title LIKE ? OR t.details LIKE ?
        ORDER BY tg.name, t.due_date IS NULL, t.due_date, t.title
    ");
    $stmt->execute([$search_term, $search_term]);
    $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($all_results as $result) {
        if (!isset($search_results_by_group[$result['group_id']])) {
            $search_results_by_group[$result['group_id']] = [
                'group_name' => $result['group_name'],
                'tasks' => []
            ];
        }
        $search_results_by_group[$result['group_id']]['tasks'][] = $result;
    }
} elseif ($selected_group) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE group_id = ? ORDER BY due_date IS NULL, due_date, title");
    $stmt->execute([$selected_group]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$selected_group_name = '';
if ($selected_group) {
    $stmt = $pdo->prepare("SELECT name FROM task_groups WHERE id = ?");
    $stmt->execute([$selected_group]);
    $selected_group_name = $stmt->fetchColumn();
}
// Prepare view data
$context = [
    'groups' => $groups,
    'selected_group' => $selected_group,
    'selected_group_name' => $selected_group_name,
    'tasks' => $tasks,
    'is_searching' => $is_searching,
    'search_query' => $search_query,
    'search_results_by_group' => $search_results_by_group,
    'message' => $message,
];

// Compute display dates for tasks
foreach ($context['tasks'] as &$task) {
    $task['display_date'] = formatDueDate($task['due_date']);
    $task['is_due'] = isTaskDue($task['due_date']);
}
unset($task);

foreach ($context['search_results_by_group'] as &$groupData) {
    foreach ($groupData['tasks'] as &$task) {
        $task['display_date'] = formatDueDate($task['due_date']);
        $task['is_due'] = isTaskDue($task['due_date']);
    }
    unset($task);
}
unset($groupData);

echo $twig->render('tasks.twig', $context);
