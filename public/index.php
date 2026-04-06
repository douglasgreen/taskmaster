<?php

declare(strict_types=1);

use DouglasGreen\TaskMaster\Controller\GroupController;
use DouglasGreen\TaskMaster\Controller\RecurringTaskController;
use DouglasGreen\TaskMaster\Controller\TaskController;
use DouglasGreen\TaskMaster\Persistence\RecurringTaskRepository;
use DouglasGreen\TaskMaster\Persistence\TaskGroupRepository;
use DouglasGreen\TaskMaster\Helper\DateHelper;
use DouglasGreen\TaskMaster\Persistence\TaskRepository;

['pdo' => $pdo, 'twig' => $twig] = require __DIR__ . '/../bootstrap.php';

$taskRepo = new TaskRepository($pdo);
$groupRepo = new TaskGroupRepository($pdo);
$recurringTaskRepo = new RecurringTaskRepository($pdo);

$taskController = new TaskController($taskRepo, $groupRepo);
$groupController = new GroupController($groupRepo);
$recurringTaskController = new RecurringTaskController($recurringTaskRepo);

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    $action = $_GET['ajax'];
    $isRecurring = ($_GET['type'] ?? '') === 'recurring';

    if ($isRecurring && in_array($action, ['add_task', 'edit_task', 'delete_task', 'get_task'], true)) {
        $recurringTaskController->handleAjax($action);
    } elseif (in_array($action, ['add_task', 'edit_task', 'delete_task', 'move_task', 'get_task'], true)) {
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
    }
}

// Simple Front Controller Routing
$page = $_GET['page'] ?? 'tasks';

match ($page) {
    'recurring' => (function () use ($twig, $recurringTaskRepo) {
        $search_query = $_GET['search'] ?? '';
        $is_searching = !empty($search_query);

        if ($is_searching) {
            $rows = $recurringTaskRepo->search($search_query);
        } else {
            $rows = $recurringTaskRepo->findAll();
        }

        $viewTasks = [];
        foreach ($rows as $row) {
            $schedule = '';
            if (!empty($row['days_of_week'])) {
                $map = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                $days = [];
                $tokens = explode('|', (string) $row['days_of_week']);
                foreach ($tokens as $token) {
                    if (str_contains($token, '-')) {
                        [$s, $e] = explode('-', $token);
                        $days[] = ($map[$s] ?? $s) . '-' . ($map[$e] ?? $e);
                    } else { $days[] = $map[$token] ?? $token; }
                }
                $schedule = 'Every ' . implode(', ', $days);
            } elseif (!empty($row['days_of_month'])) { $schedule = 'Monthly on day(s): ' . str_replace('|', ', ', $row['days_of_month']); }
            elseif (!empty($row['days_of_year'])) { $schedule = 'Yearly on: ' . str_replace('|', ', ', $row['days_of_year']); }
            else { $schedule = 'Daily'; }

            if (!empty($row['time_of_day'])) { $schedule .= ' at ' . str_replace('|', ', ', $row['time_of_day']); }
            if (!empty($row['recur_start'])) { $schedule .= ' starting ' . $row['recur_start']; }
            if (!empty($row['recur_end'])) { $schedule .= ' until ' . $row['recur_end']; }

            $viewTasks[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'details' => $row['details'] ?? '',
                'schedule' => $schedule,
                'last_reminded_at' => $row['last_reminded_at'] ?: 'Never',
            ];
        }

        echo $twig->render('recurring.twig', [
            'search_query' => $search_query,
            'is_searching' => $is_searching,
            'view_tasks' => $viewTasks,
        ]);
    })(),
    default => (function () use ($twig, $taskRepo, $groupRepo) {
        $selected_group = $_GET['group'] ?? null;
        $search_query = $_GET['search'] ?? '';
        $message = '';

        if (isset($_GET['msg']) && $_GET['msg'] === 'group_added') {
            $message = 'Group added successfully.';
        }

        if ($selected_group) {
            $group = $groupRepo->findById((int) $selected_group);
            if (!$group) { $selected_group = null; }
        }

        $rawGroups = $groupRepo->findAll();
        $groups = [];
        foreach ($rawGroups as $group) {
            $group['due_count'] = $taskRepo->countDueByGroupId((int) $group['id']);
            $groups[] = $group;
        }

        $tasks = [];
        $search_results_by_group = [];
        $is_searching = !empty($search_query);

        if ($is_searching) {
            $all_results = $taskRepo->search($search_query);
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
            $tasks = $taskRepo->findByGroupId((int) $selected_group);
        }

        $selected_group_name = '';
        if ($selected_group) {
            $group = $groupRepo->findById((int) $selected_group);
            $selected_group_name = $group['name'] ?? '';
        }

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

        foreach ($context['tasks'] as &$task) {
            $task['display_date'] = DateHelper::formatDueDate($task['due_date']);
            $task['is_due'] = DateHelper::isTaskDue($task['due_date']);
        }
        unset($task);

        foreach ($context['search_results_by_group'] as &$groupData) {
            foreach ($groupData['tasks'] as &$task) {
                $task['display_date'] = DateHelper::formatDueDate($task['due_date']);
                $task['is_due'] = DateHelper::isTaskDue($task['due_date']);
            }
            unset($task);
        }
        unset($groupData);

        echo $twig->render('tasks.twig', $context);
    })(),
};

