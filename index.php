<?php

$configFile = __DIR__ . '/config/config.ini';
if (! file_exists($configFile)) {
    die("Config file not found. Please create config/config.ini from config.ini.sample\n");
}
$config = parse_ini_file($configFile, true);
$connection = $config['connection'];
$host = $connection['host'];
$port = $connection['port'];
$database = $connection['db'];
$user = $connection['user'];
$password = $connection['pass'];
if ($host === '~' || $database === '~' || $user === '~' || $password === '~') {
    die("Config not set up. Please update config.ini\n");
}
$dsn = "mysql:host={$host};port={$port};dbname={$database}";
$pdo = new PDO($dsn, $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Helper Functions
function formatDueDate($due_date_str) {
    if (empty($due_date_str) || $due_date_str === '0000-00-00') {
        return '';
    }
    $due = new DateTime($due_date_str);
    $now = new DateTime();
    $now->setTime(0, 0, 0);
    $due->setTime(0, 0, 0);
    $interval = $now->diff($due);
    $days_from_now = $interval->invert ? -$interval->days : $interval->days;
    $abs_days = abs($days_from_now);

    if ($abs_days > 99) {
        return $due_date_str;
    }
    if ($days_from_now < 0) {
        if ($abs_days == 1) {
            return 'yesterday';
        } else {
            return $abs_days . ' days ago';
        }
    } else {
        if ($days_from_now == 0) {
            return 'today';
        } elseif ($days_from_now == 1) {
            return 'tomorrow';
        } else {
            return 'in ' . $days_from_now . ' days';
        }
    }
}

function formatDetails($details) {
    if (empty($details)) return '';
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
    $escaped = nl2br(htmlspecialchars($processed, ENT_QUOTES, 'UTF-8'));
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

function isTaskDue($due_date_str) {
    if (empty($due_date_str) || $due_date_str === '0000-00-00') {
        return false;
    }
    $due = new DateTime($due_date_str);
    $now = new DateTime();
    $due->setTime(0, 0, 0);
    $now->setTime(0, 0, 0);
    return $due <= $now;
}

function getDueCount($pdo, $group_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ? AND due_date IS NOT NULL AND due_date != '0000-00-00' AND due_date <= CURDATE()");
    $stmt->execute([$group_id]);
    return (int)$stmt->fetchColumn();
}

// AJAX Handler
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'add_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $group_id = (int)$_POST['group_id'];
        $title = trim($_POST['title']);
        $details = trim($_POST['details'] ?? '');
        $due_date = $_POST['due_date'] ?: null;

        if (!empty($title)) {
            $stmt = $pdo->prepare("INSERT INTO tasks (group_id, title, details, due_date, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$group_id, $title, $details, $due_date]);
            $task_id = $pdo->lastInsertId();

            echo json_encode(['success' => true, 'task_id' => $task_id, 'message' => 'Task added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task title is required']);
        }
        exit;
    }

    if ($_GET['ajax'] === 'edit_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $task_id = (int)$_POST['task_id'];
        $title = trim($_POST['title']);
        $details = trim($_POST['details'] ?? '');
        $due_date = $_POST['due_date'] ?: null;

        if (!empty($title)) {
            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, details = ?, due_date = ? WHERE id = ?");
            $stmt->execute([$title, $details, $due_date, $task_id]);

            echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task title is required']);
        }
        exit;
    }

    if ($_GET['ajax'] === 'delete_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $task_id = (int)$_POST['task_id'];

        $stmt = $pdo->prepare("SELECT group_id FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $group_id = $stmt->fetchColumn();

        if ($group_id) {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);

            // Check if group is now empty
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ?");
            $stmt->execute([$group_id]);
            $group_empty = ($stmt->fetchColumn() == 0);

            if ($group_empty) {
                $stmt = $pdo->prepare("DELETE FROM task_groups WHERE id = ?");
                $stmt->execute([$group_id]);
            }

            echo json_encode(['success' => true, 'group_empty' => $group_empty, 'message' => 'Task deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
        exit;
    }

    if ($_GET['ajax'] === 'rename_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $group_id = (int)$_POST['group_id'];
        $name = trim($_POST['name']);

        if (!empty($name)) {
            $stmt = $pdo->prepare("UPDATE task_groups SET name = ? WHERE id = ?");
            $stmt->execute([$name, $group_id]);

            echo json_encode(['success' => true, 'message' => 'Group renamed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Group name is required']);
        }
        exit;
    }

    if ($_GET['ajax'] === 'move_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $task_id = (int)$_POST['task_id'];
        $new_group_id = (int)$_POST['new_group_id'];

        $stmt = $pdo->prepare("SELECT group_id FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $old_group_id = $stmt->fetchColumn();

        if ($old_group_id) {
            $stmt = $pdo->prepare("UPDATE tasks SET group_id = ? WHERE id = ?");
            $stmt->execute([$new_group_id, $task_id]);

            // Check if old group is now empty
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE group_id = ?");
            $stmt->execute([$old_group_id]);
            $old_group_empty = ($stmt->fetchColumn() == 0);

            if ($old_group_empty) {
                $stmt = $pdo->prepare("DELETE FROM task_groups WHERE id = ?");
                $stmt->execute([$old_group_id]);
            }

            echo json_encode(['success' => true, 'old_group_empty' => $old_group_empty, 'message' => 'Task moved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
        exit;
    }

    if ($_GET['ajax'] === 'get_task' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $task_id = (int)$_GET['task_id'];

        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
        exit;
    }
}

// Handle traditional POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    if (isset($_POST['add_group'])) {
        $name = trim($_POST['group_name']);
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO task_groups (name, created_at) VALUES (?, NOW())");
                $stmt->execute([$name]);
                $new_group_id = $pdo->lastInsertId();
                header("Location: ?group=$new_group_id&msg=group_added");
                exit;
            } catch (PDOException $e) {
                $error_message = 'Error adding group: ' . $e->getMessage();
            }
        }
    }
}

$selected_group = $_GET['group'] ?? null;
$search_query = $_GET['search'] ?? '';
$message = '';

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'group_added':
            $message = 'Group added successfully.';
            break;
    }
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
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$selected_group_name = '';
if ($selected_group) {
    $stmt = $pdo->prepare("SELECT name FROM task_groups WHERE id = ?");
    $stmt->execute([$selected_group]);
    $selected_group_name = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-hover: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        body {
            background: var(--gradient-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Skip Navigation Link */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: #000;
            color: white;
            padding: 8px;
            text-decoration: none;
            z-index: 100;
        }

        .skip-link:focus {
            top: 0;
        }

        /* Header */
        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 1030;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header h1 {
            font-size: 1.75rem;
            margin: 0;
        }

        .mobile-menu-toggle {
            display: none;
            background: transparent;
            border: 2px solid white;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.25rem;
        }

        .mobile-menu-toggle:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Main Container */
        .main-container {
            height: calc(100vh - var(--header-height));
            overflow: hidden;
        }

        /* Left Sidebar */
        .left-panel {
            background: white;
            height: 100%;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            width: var(--sidebar-width);
            position: relative;
            transition: transform 0.3s ease;
        }

        .left-panel-header {
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .left-panel-header h5 {
            margin: 0;
            font-weight: 600;
            color: #495057;
        }

        .groups-container {
            padding: 15px;
        }

        .list-group-item-action {
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
            position: relative;
            padding-right: 50px;
        }

        .list-group-item-action:hover {
            background-color: #f0f3ff;
            color: #764ba2;
            transform: translateX(3px);
        }

        .list-group-item-action.active {
            background: var(--gradient-primary);
            border-color: #667eea;
            color: white;
        }

        .list-group-item-action:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        .group-item-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .group-name {
            flex: 1;
        }

        .due-badge {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            background: #dc3545;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .active .due-badge {
            background: rgba(255,255,255,0.3);
        }

        .group-actions {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .list-group-item-action:hover .group-actions {
            opacity: 1;
        }

        .list-group-item-action.active .group-actions {
            opacity: 1;
        }

        .group-rename-btn {
            background: transparent;
            border: none;
            color: #6c757d;
            padding: 2px 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .active .group-rename-btn {
            color: white;
        }

        .group-rename-btn:hover {
            color: #495057;
        }

        .active .group-rename-btn:hover {
            color: rgba(255,255,255,0.8);
        }

        .add-group-form {
            padding: 15px;
            border-top: 2px solid #e9ecef;
            position: sticky;
            bottom: 0;
            background: white;
        }

        /* Right Panel */
        .right-panel {
            background: #f8f9fa;
            height: 100%;
            overflow-y: auto;
            flex: 1;
        }

        .right-panel-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Breadcrumbs */
        .breadcrumb-nav {
            background: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .breadcrumb {
            margin: 0;
        }

        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            text-decoration: underline;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .card-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #495057;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 25px;
        }

        /* Buttons */
        .btn-gradient {
            background: var(--gradient-primary);
            border: none;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-gradient:hover {
            background: var(--gradient-hover);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-gradient:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        .btn-add-task {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
            z-index: 1020;
        }

        /* Tables */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: #343a40;
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }

        .table tbody tr {
            transition: background-color 0.2s;
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9ff;
        }

        .table td {
            vertical-align: top;
            padding: 12px 15px;
        }

        /* Task Cards (Mobile) */
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .task-card-title {
            font-weight: 600;
            color: #212529;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .task-card-details {
            color: #6c757d;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        .task-card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .task-card-date {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .task-card-actions {
            display: flex;
            gap: 8px;
        }

        /* Search */
        .search-form {
            max-width: 400px;
        }

        .search-group-header {
            background: var(--gradient-primary);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            border-radius: 12px 12px 0 0;
            font-size: 1.1rem;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 1050;
        }

        .toast {
            min-width: 300px;
        }

        /* Modals */
        .modal-content {
            border-radius: 12px;
            border: none;
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 12px 12px 0 0;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Keyboard Shortcuts Help */
        .keyboard-shortcuts {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1040;
        }

        .shortcuts-toggle {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .shortcuts-toggle:hover {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }

        .shortcuts-list {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            margin-bottom: 10px;
            display: none;
        }

        .shortcuts-list.show {
            display: block;
        }

        .shortcut-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .shortcut-item:last-child {
            border-bottom: none;
        }

        .shortcut-key {
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid #dee2e6;
        }

        /* Mobile Responsive */
        @media (max-width: 991px) {
            .mobile-menu-toggle {
                display: block;
            }

            .left-panel {
                position: fixed;
                left: 0;
                top: var(--header-height);
                height: calc(100vh - var(--header-height));
                z-index: 1025;
                transform: translateX(-100%);
            }

            .left-panel.show {
                transform: translateX(0);
            }

            .sidebar-overlay {
                position: fixed;
                top: var(--header-height);
                left: 0;
                width: 100%;
                height: calc(100vh - var(--header-height));
                background: rgba(0,0,0,0.5);
                z-index: 1024;
                display: none;
            }

            .sidebar-overlay.show {
                display: block;
            }

            .right-panel-content {
                padding: 20px 15px;
            }

            .btn-add-task {
                bottom: 20px;
                right: 20px;
                width: 56px;
                height: 56px;
            }

            /* Hide table, show cards on mobile */
            .table-view {
                display: none;
            }

            .card-view {
                display: block;
            }

            .keyboard-shortcuts {
                display: none;
            }
        }

        @media (min-width: 992px) {
            .table-view {
                display: block;
            }

            .card-view {
                display: none;
            }
        }

        @media (max-width: 767px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .search-form {
                max-width: 100%;
            }
        }

        /* Focus Visible Enhancement */
        *:focus-visible {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }

        .spinner-overlay.show {
            display: flex;
        }
    </style>
</head>
<body>
    <!-- Skip Navigation -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner" role="alert" aria-live="assertive" aria-label="Loading">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Header -->
    <header class="header" role="banner">
        <h1>Task Manager</h1>
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
    </header>

    <!-- Toast Container -->
    <div class="toast-container" aria-live="polite" aria-atomic="true" id="toastContainer"></div>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid main-container">
        <div class="row h-100">
            <!-- Left Panel: Groups -->
            <aside class="left-panel" id="leftPanel" role="navigation" aria-label="Task groups">
                <div class="left-panel-header">
                    <h5>Groups</h5>
                </div>

                <div class="groups-container">
                    <?php if (empty($groups)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-folder-plus text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3 mb-0">No groups yet.<br>Add one below.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($groups as $group): ?>
                                <a href="?group=<?php echo $group['id']; ?>"
                                   class="list-group-item list-group-item-action <?php echo $selected_group == $group['id'] ? 'active' : ''; ?>"
                                   aria-current="<?php echo $selected_group == $group['id'] ? 'page' : 'false'; ?>">
                                    <span class="group-name"><?php echo htmlspecialchars($group['name']); ?></span>
                                    <?php if ($group['due_count'] > 0): ?>
                                        <span class="due-badge" title="<?php echo $group['due_count']; ?> due task<?php echo $group['due_count'] > 1 ? 's' : ''; ?>">
                                            <?php echo $group['due_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="group-actions">
                                        <button class="group-rename-btn"
                                                data-group-id="<?php echo $group['id']; ?>"
                                                data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                                title="Rename group"
                                                aria-label="Rename group <?php echo htmlspecialchars($group['name']); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="add-group-form">
                    <form method="post" id="addGroupForm">
                        <div class="input-group">
                            <input type="text"
                                   name="group_name"
                                   id="groupNameInput"
                                   class="form-control"
                                   placeholder="New group name"
                                   required
                                   aria-label="New group name">
                            <button type="submit" name="add_group" class="btn btn-gradient" aria-label="Add group">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </aside>

            <!-- Right Panel: Tasks -->
            <main class="right-panel" id="main-content" role="main">
                <div class="right-panel-content">
                    <?php if (!$is_searching): ?>
                        <?php if ($selected_group): ?>
                            <!-- Breadcrumbs -->
                            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="?">Home</a></li>
                                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($selected_group_name); ?></li>
                                </ol>
                            </nav>

                            <!-- Group Header -->
                            <div class="card">
                                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <h2 class="mb-0"><?php echo htmlspecialchars($selected_group_name); ?></h2>
                                    <form method="get" class="search-form d-flex gap-2">
                                        <input type="text"
                                               name="search"
                                               class="form-control"
                                               placeholder="Search tasks..."
                                               aria-label="Search tasks">
                                        <button type="submit" class="btn btn-gradient" aria-label="Search">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <?php if (empty($tasks)): ?>
                                <!-- Empty State -->
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-clipboard-check"></i>
                                    </div>
                                    <h3>No tasks yet</h3>
                                    <p>Get started by adding your first task to this group.</p>
                                    <button class="btn btn-gradient btn-lg" id="emptyStateAddBtn">
                                        <i class="bi bi-plus-lg"></i> Add Your First Task
                                    </button>
                                </div>
                            <?php else: ?>
                                <!-- Table View (Desktop/Tablet) -->
                                <div class="card table-view">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" role="table">
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="width: 80px">Delete</th>
                                                    <th scope="col" style="width: 80px">Edit</th>
                                                    <th scope="col">Title</th>
                                                    <th scope="col">Details</th>
                                                    <th scope="col" style="width: 150px">Due Date</th>
                                                    <th scope="col" style="width: 120px">Move To</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tasks as $task): ?>
                                                    <?php
                                                    $due_date = $task['due_date'];
                                                    $display_date = formatDueDate($due_date);
                                                    $is_due = isTaskDue($due_date);
                                                    ?>
                                                    <tr data-task-id="<?php echo $task['id']; ?>">
                                                        <td>
                                                            <button class="btn btn-sm btn-danger delete-task-btn"
                                                                    data-task-id="<?php echo $task['id']; ?>"
                                                                    data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                                    aria-label="Delete task: <?php echo htmlspecialchars($task['title']); ?>">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary edit-task-btn"
                                                                    data-task-id="<?php echo $task['id']; ?>"
                                                                    aria-label="Edit task: <?php echo htmlspecialchars($task['title']); ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                        <td><?php echo formatDetails($task['details']); ?></td>
                                                        <td>
                                                            <?php if ($is_due): ?>
                                                                <strong class="text-danger"><?php echo $display_date; ?></strong>
                                                            <?php else: ?>
                                                                <?php echo $display_date; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <select class="form-select form-select-sm move-task-select"
                                                                    data-task-id="<?php echo $task['id']; ?>"
                                                                    aria-label="Move task to another group">
                                                                <option value="">Move to...</option>
                                                                <?php foreach ($groups as $group): ?>
                                                                    <?php if ($group['id'] != $selected_group): ?>
                                                                        <option value="<?php echo $group['id']; ?>">
                                                                            <?php echo htmlspecialchars($group['name']); ?>
                                                                        </option>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Card View (Mobile) -->
                                <div class="card-view">
                                    <?php foreach ($tasks as $task): ?>
                                        <?php
                                        $due_date = $task['due_date'];
                                        $display_date = formatDueDate($due_date);
                                        $is_due = isTaskDue($due_date);
                                        ?>
                                        <div class="task-card" data-task-id="<?php echo $task['id']; ?>">
                                            <div class="task-card-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <?php if (!empty($task['details'])): ?>
                                                <div class="task-card-details"><?php echo formatDetails($task['details']); ?></div>
                                            <?php endif; ?>
                                            <div class="task-card-meta">
                                                <div class="task-card-date">
                                                    <?php if (!empty($display_date)): ?>
                                                        <i class="bi bi-calendar"></i>
                                                        <?php if ($is_due): ?>
                                                            <strong class="text-danger"><?php echo $display_date; ?></strong>
                                                        <?php else: ?>
                                                            <?php echo $display_date; ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="task-card-actions">
                                                    <button class="btn btn-sm btn-outline-primary edit-task-btn"
                                                            data-task-id="<?php echo $task['id']; ?>"
                                                            aria-label="Edit task">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                                type="button"
                                                                data-bs-toggle="dropdown"
                                                                aria-expanded="false"
                                                                aria-label="More actions">
                                                            <i class="bi bi-three-dots"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php foreach ($groups as $group): ?>
                                                                <?php if ($group['id'] != $selected_group): ?>
                                                                    <li>
                                                                        <a class="dropdown-item move-task-link"
                                                                           href="#"
                                                                           data-task-id="<?php echo $task['id']; ?>"
                                                                           data-group-id="<?php echo $group['id']; ?>">
                                                                            Move to <?php echo htmlspecialchars($group['name']); ?>
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item text-danger delete-task-btn"
                                                                   href="#"
                                                                   data-task-id="<?php echo $task['id']; ?>"
                                                                   data-task-title="<?php echo htmlspecialchars($task['title']); ?>">
                                                                    <i class="bi bi-trash"></i> Delete
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Floating Add Button -->
                            <button class="btn btn-gradient btn-add-task"
                                    id="addTaskBtn"
                                    aria-label="Add new task"
                                    title="Add new task (Press 'n')">
                                <i class="bi bi-plus-lg"></i>
                            </button>

                        <?php else: ?>
                            <!-- No Group Selected -->
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-folder-open"></i>
                                </div>
                                <h3>Welcome to Task Manager</h3>
                                <p>Select a group from the sidebar to view and manage your tasks,<br>or search across all groups.</p>
                                <form method="get" class="search-form mx-auto">
                                    <div class="input-group input-group-lg">
                                        <input type="text"
                                               name="search"
                                               class="form-control"
                                               placeholder="Search all tasks..."
                                               aria-label="Search all tasks">
                                        <button type="submit" class="btn btn-gradient" aria-label="Search">
                                            <i class="bi bi-search"></i> Search
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Search Results -->
                        <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="?">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Search Results</li>
                            </ol>
                        </nav>

                        <div class="card">
                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <h2 class="mb-0">Search: "<?php echo htmlspecialchars($search_query); ?>"</h2>
                                <div class="d-flex gap-2">
                                    <form method="get" class="search-form d-flex gap-2">
                                        <input type="text"
                                               name="search"
                                               class="form-control"
                                               placeholder="Search tasks..."
                                               value="<?php echo htmlspecialchars($search_query); ?>"
                                               aria-label="Search tasks">
                                        <button type="submit" class="btn btn-gradient" aria-label="Search">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </form>
                                    <a href="?" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($search_results_by_group)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-search"></i>
                                </div>
                                <h3>No results found</h3>
                                <p>No tasks match your search query.</p>
                                <a href="?" class="btn btn-gradient">Back to Home</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($search_results_by_group as $group_id => $group_data): ?>
                                <div class="mb-4">
                                    <div class="search-group-header">
                                        <?php echo htmlspecialchars($group_data['group_name']); ?>
                                    </div>
                                    <div class="card" style="border-radius: 0 0 12px 12px;">
                                        <!-- Table View -->
                                        <div class="table-responsive table-view">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th scope="col" style="width: 80px">Delete</th>
                                                        <th scope="col" style="width: 80px">Edit</th>
                                                        <th scope="col">Title</th>
                                                        <th scope="col">Details</th>
                                                        <th scope="col" style="width: 150px">Due Date</th>
                                                        <th scope="col" style="width: 120px">Move To</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($group_data['tasks'] as $task): ?>
                                                        <?php
                                                        $due_date = $task['due_date'];
                                                        $display_date = formatDueDate($due_date);
                                                        $is_due = isTaskDue($due_date);
                                                        ?>
                                                        <tr data-task-id="<?php echo $task['id']; ?>">
                                                            <td>
                                                                <button class="btn btn-sm btn-danger delete-task-btn"
                                                                        data-task-id="<?php echo $task['id']; ?>"
                                                                        data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                                        aria-label="Delete task">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary edit-task-btn"
                                                                        data-task-id="<?php echo $task['id']; ?>"
                                                                        aria-label="Edit task">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                            <td><?php echo formatDetails($task['details']); ?></td>
                                                            <td>
                                                                <?php if ($is_due): ?>
                                                                    <strong class="text-danger"><?php echo $display_date; ?></strong>
                                                                <?php else: ?>
                                                                    <?php echo $display_date; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <select class="form-select form-select-sm move-task-select"
                                                                        data-task-id="<?php echo $task['id']; ?>"
                                                                        aria-label="Move task to another group">
                                                                    <option value="">Move to...</option>
                                                                    <?php foreach ($groups as $group): ?>
                                                                        <?php if ($group['id'] != $task['group_id']): ?>
                                                                            <option value="<?php echo $group['id']; ?>">
                                                                                <?php echo htmlspecialchars($group['name']); ?>
                                                                            </option>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Card View (Mobile) -->
                                        <div class="card-view p-3">
                                            <?php foreach ($group_data['tasks'] as $task): ?>
                                                <?php
                                                $due_date = $task['due_date'];
                                                $display_date = formatDueDate($due_date);
                                                $is_due = isTaskDue($due_date);
                                                ?>
                                                <div class="task-card" data-task-id="<?php echo $task['id']; ?>">
                                                    <div class="task-card-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                                    <?php if (!empty($task['details'])): ?>
                                                        <div class="task-card-details"><?php echo formatDetails($task['details']); ?></div>
                                                    <?php endif; ?>
                                                    <div class="task-card-meta">
                                                        <div class="task-card-date">
                                                            <?php if (!empty($display_date)): ?>
                                                                <i class="bi bi-calendar"></i>
                                                                <?php if ($is_due): ?>
                                                                    <strong class="text-danger"><?php echo $display_date; ?></strong>
                                                                <?php else: ?>
                                                                    <?php echo $display_date; ?>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="task-card-actions">
                                                            <button class="btn btn-sm btn-outline-primary edit-task-btn"
                                                                    data-task-id="<?php echo $task['id']; ?>"
                                                                    aria-label="Edit task">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger delete-task-btn"
                                                                    data-task-id="<?php echo $task['id']; ?>"
                                                                    data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                                    aria-label="Delete task">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add/Edit Task Modal -->
    <div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskModalLabel">Add New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="taskForm">
                    <div class="modal-body">
                        <input type="hidden" id="taskId" name="task_id">
                        <input type="hidden" id="taskGroupId" name="group_id" value="<?php echo $selected_group ?? ''; ?>">

                        <div class="mb-3">
                            <label for="taskTitle" class="form-label">Task Title <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   id="taskTitle"
                                   name="title"
                                   required
                                   aria-required="true"
                                   autofocus>
                            <div class="invalid-feedback">Please enter a task title.</div>
                        </div>

                        <div class="mb-3">
                            <label for="taskDetails" class="form-label">Task Details</label>
                            <textarea class="form-control"
                                      id="taskDetails"
                                      name="details"
                                      rows="4"
                                      placeholder="Enter additional details or paste URLs..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="taskDueDate" class="form-label">Due Date</label>
                            <input type="date"
                                   class="form-control"
                                   id="taskDueDate"
                                   name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient" id="saveTaskBtn">
                            <i class="bi bi-check-lg"></i> <span id="saveTaskBtnText">Add Task</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rename Group Modal -->
    <div class="modal fade" id="renameGroupModal" tabindex="-1" aria-labelledby="renameGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="renameGroupModalLabel">Rename Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="renameGroupForm">
                    <div class="modal-body">
                        <input type="hidden" id="renameGroupId" name="group_id">

                        <div class="mb-3">
                            <label for="renameGroupName" class="form-label">Group Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   id="renameGroupName"
                                   name="name"
                                   required
                                   aria-required="true"
                                   autofocus>
                            <div class="invalid-feedback">Please enter a group name.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient">
                            <i class="bi bi-check-lg"></i> Rename Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0) invert(1);"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete "<strong id="deleteTaskTitle"></strong>"?</p>
                    <p class="text-muted mt-2 mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash"></i> Delete Task
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Keyboard Shortcuts -->
    <div class="keyboard-shortcuts">
        <div class="shortcuts-list" id="shortcutsList" role="dialog" aria-label="Keyboard shortcuts">
            <h6 class="mb-3">Keyboard Shortcuts</h6>
            <div class="shortcut-item">
                <span>Add new task</span>
                <kbd class="shortcut-key">N</kbd>
            </div>
            <div class="shortcut-item">
                <span>Search</span>
                <kbd class="shortcut-key">/</kbd>
            </div>
            <div class="shortcut-item">
                <span>Toggle sidebar</span>
                <kbd class="shortcut-key">S</kbd>
            </div>
            <div class="shortcut-item">
                <span>Close dialog</span>
                <kbd class="shortcut-key">ESC</kbd>
            </div>
            <div class="shortcut-item">
                <span>Show shortcuts</span>
                <kbd class="shortcut-key">?</kbd>
            </div>
        </div>
        <button class="shortcuts-toggle"
                id="shortcutsToggle"
                aria-label="Show keyboard shortcuts"
                title="Keyboard shortcuts (Press ?)">
            <i class="bi bi-question-lg"></i>
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();

            const iconMap = {
                'success': 'check-circle-fill',
                'error': 'exclamation-triangle-fill',
                'info': 'info-circle-fill'
            };

            const bgMap = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'info': 'bg-primary'
            };

            const toastHTML = `
                <div class="toast align-items-center text-white ${bgMap[type]} border-0" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-${iconMap[type]} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 4000 });
            toast.show();

            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        // Loading spinner
        function showLoading() {
            document.getElementById('loadingSpinner').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').classList.remove('show');
        }

        // Mobile sidebar toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const leftPanel = document.getElementById('leftPanel');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            const isOpen = leftPanel.classList.contains('show');
            if (isOpen) {
                leftPanel.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
            } else {
                leftPanel.classList.add('show');
                sidebarOverlay.classList.add('show');
                mobileMenuToggle.setAttribute('aria-expanded', 'true');
            }
        }

        mobileMenuToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking a group link on mobile
        document.querySelectorAll('.list-group-item-action').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    setTimeout(toggleSidebar, 100);
                }
            });
        });

        // Task Modal
        const taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
        const taskForm = document.getElementById('taskForm');
        const taskModalLabel = document.getElementById('taskModalLabel');
        const saveTaskBtnText = document.getElementById('saveTaskBtnText');
        const taskIdInput = document.getElementById('taskId');
        const taskTitleInput = document.getElementById('taskTitle');
        const taskDetailsInput = document.getElementById('taskDetails');
        const taskDueDateInput = document.getElementById('taskDueDate');
        const taskGroupIdInput = document.getElementById('taskGroupId');

        // Open add task modal
        document.getElementById('addTaskBtn')?.addEventListener('click', () => {
            openTaskModal();
        });

        document.getElementById('emptyStateAddBtn')?.addEventListener('click', () => {
            openTaskModal();
        });

        function openTaskModal(taskId = null) {
            taskForm.reset();
            taskForm.classList.remove('was-validated');

            if (taskId) {
                // Edit mode
                taskModalLabel.textContent = 'Edit Task';
                saveTaskBtnText.textContent = 'Update Task';

                // Fetch task data
                showLoading();
                fetch(`?ajax=get_task&task_id=${taskId}`)
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            taskIdInput.value = data.task.id;
                            taskTitleInput.value = data.task.title;
                            taskDetailsInput.value = data.task.details || '';
                            taskDueDateInput.value = data.task.due_date || '';
                            taskGroupIdInput.value = data.task.group_id;
                            taskModal.show();
                        } else {
                            showToast(data.message || 'Error loading task', 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showToast('Error loading task data', 'error');
                        console.error('Error:', error);
                    });
            } else {
                // Add mode
                taskModalLabel.textContent = 'Add New Task';
                saveTaskBtnText.textContent = 'Add Task';
                taskIdInput.value = '';
                taskModal.show();
            }
        }

        // Edit task buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.edit-task-btn')) {
                const btn = e.target.closest('.edit-task-btn');
                const taskId = btn.dataset.taskId;
                openTaskModal(taskId);
            }
        });

        // Save task (Add/Edit)
        taskForm.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!taskForm.checkValidity()) {
                e.stopPropagation();
                taskForm.classList.add('was-validated');
                return;
            }

            const formData = new FormData(taskForm);
            const taskId = taskIdInput.value;
            const isEdit = taskId !== '';
            const ajaxAction = isEdit ? 'edit_task' : 'add_task';

            showLoading();

            fetch(`?ajax=${ajaxAction}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    taskModal.hide();

                    // Reload page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showToast(data.message || 'Error saving task', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Error saving task', 'error');
                console.error('Error:', error);
            });
        });

        // Delete Task Modal
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        const deleteTaskTitle = document.getElementById('deleteTaskTitle');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let deleteTaskId = null;

        document.addEventListener('click', (e) => {
            if (e.target.closest('.delete-task-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.delete-task-btn');
                deleteTaskId = btn.dataset.taskId;
                const taskTitle = btn.dataset.taskTitle;
                deleteTaskTitle.textContent = taskTitle;
                deleteModal.show();
            }
        });

        confirmDeleteBtn.addEventListener('click', () => {
            if (!deleteTaskId) return;

            const formData = new FormData();
            formData.append('task_id', deleteTaskId);

            showLoading();

            fetch('?ajax=delete_task', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    deleteModal.hide();

                    if (data.group_empty) {
                        // Group was deleted, redirect to home
                        setTimeout(() => {
                            window.location.href = '?';
                        }, 500);
                    } else {
                        // Remove task from UI
                        const taskRow = document.querySelector(`tr[data-task-id="${deleteTaskId}"]`);
                        const taskCard = document.querySelector(`.task-card[data-task-id="${deleteTaskId}"]`);

                        if (taskRow) taskRow.remove();
                        if (taskCard) taskCard.remove();

                        // Reload to update due badges
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                } else {
                    showToast(data.message || 'Error deleting task', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Error deleting task', 'error');
                console.error('Error:', error);
            });
        });

        // Move Task
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('move-task-select')) {
                const select = e.target;
                const taskId = select.dataset.taskId;
                const newGroupId = select.value;

                if (!newGroupId) return;

                const formData = new FormData();
                formData.append('task_id', taskId);
                formData.append('new_group_id', newGroupId);

                showLoading();

                fetch('?ajax=move_task', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast(data.message, 'success');

                        // Reload page to reflect changes
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        showToast(data.message || 'Error moving task', 'error');
                        select.value = '';
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Error moving task', 'error');
                    console.error('Error:', error);
                    select.value = '';
                });
            }
        });

        // Move task from mobile dropdown
        document.addEventListener('click', (e) => {
            if (e.target.closest('.move-task-link')) {
                e.preventDefault();
                const link = e.target.closest('.move-task-link');
                const taskId = link.dataset.taskId;
                const newGroupId = link.dataset.groupId;

                const formData = new FormData();
                formData.append('task_id', taskId);
                formData.append('new_group_id', newGroupId);

                showLoading();

                fetch('?ajax=move_task', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        showToast(data.message || 'Error moving task', 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Error moving task', 'error');
                    console.error('Error:', error);
                });
            }
        });

        // Rename Group Modal
        const renameGroupModal = new bootstrap.Modal(document.getElementById('renameGroupModal'));
        const renameGroupForm = document.getElementById('renameGroupForm');
        const renameGroupIdInput = document.getElementById('renameGroupId');
        const renameGroupNameInput = document.getElementById('renameGroupName');

        document.addEventListener('click', (e) => {
            if (e.target.closest('.group-rename-btn')) {
                e.preventDefault();
                e.stopPropagation();
                const btn = e.target.closest('.group-rename-btn');
                const groupId = btn.dataset.groupId;
                const groupName = btn.dataset.groupName;

                renameGroupIdInput.value = groupId;
                renameGroupNameInput.value = groupName;
                renameGroupForm.classList.remove('was-validated');

                renameGroupModal.show();
            }
        });

        renameGroupForm.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!renameGroupForm.checkValidity()) {
                e.stopPropagation();
                renameGroupForm.classList.add('was-validated');
                return;
            }

            const formData = new FormData(renameGroupForm);

            showLoading();

            fetch('?ajax=rename_group', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    renameGroupModal.hide();

                    // Reload page to show updated name
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showToast(data.message || 'Error renaming group', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Error renaming group', 'error');
                console.error('Error:', error);
            });
        });

        // Keyboard Shortcuts
        const shortcutsToggle = document.getElementById('shortcutsToggle');
        const shortcutsList = document.getElementById('shortcutsList');

        shortcutsToggle.addEventListener('click', () => {
            shortcutsList.classList.toggle('show');
        });

        // Close shortcuts when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.keyboard-shortcuts')) {
                shortcutsList.classList.remove('show');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Don't trigger shortcuts when typing in inputs
            if (e.target.matches('input, textarea')) {
                if (e.key === 'Escape') {
                    e.target.blur();
                }
                return;
            }

            switch (e.key.toLowerCase()) {
                case 'n':
                    // Add new task
                    if (document.getElementById('addTaskBtn')) {
                        e.preventDefault();
                        openTaskModal();
                    }
                    break;

                case '/':
                    // Focus search
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="search"]');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                    break;

                case 's':
                    // Toggle sidebar (mobile)
                    if (window.innerWidth < 992) {
                        e.preventDefault();
                        toggleSidebar();
                    }
                    break;

                case '?':
                    // Show shortcuts
                    e.preventDefault();
                    shortcutsList.classList.toggle('show');
                    break;

                case 'escape':
                    // Close modals and overlays
                    shortcutsList.classList.remove('show');
                    break;
            }
        });

        // Focus management for modals
        document.getElementById('taskModal').addEventListener('shown.bs.modal', () => {
            taskTitleInput.focus();
        });

        document.getElementById('renameGroupModal').addEventListener('shown.bs.modal', () => {
            renameGroupNameInput.focus();
            renameGroupNameInput.select();
        });

        // Announce messages to screen readers
        <?php if ($message): ?>
        showToast(<?php echo json_encode($message); ?>, 'success');
        <?php endif; ?>

        // Auto-dismiss alerts
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // Add group form handling
        document.getElementById('addGroupForm').addEventListener('submit', function(e) {
            const input = document.getElementById('groupNameInput');
            if (!input.value.trim()) {
                e.preventDefault();
                input.classList.add('is-invalid');
                showToast('Please enter a group name', 'error');
            }
        });

        // Form validation styling
        document.querySelectorAll('.needs-validation').forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });

        // Prevent accidental navigation away from forms
        let formModified = false;

        taskForm.addEventListener('input', () => {
            formModified = true;
        });

        taskForm.addEventListener('submit', () => {
            formModified = false;
        });

        document.getElementById('taskModal').addEventListener('hidden.bs.modal', () => {
            formModified = false;
        });

        // Console info for developers
        console.log('%cTask Manager', 'font-size: 20px; font-weight: bold; color: #667eea;');
        console.log('%cKeyboard Shortcuts:', 'font-weight: bold;');
        console.log('  N - Add new task');
        console.log('  / - Focus search');
        console.log('  S - Toggle sidebar (mobile)');
        console.log('  ? - Show shortcuts help');
        console.log('  ESC - Close modals/dialogs');
    </script>
</body>
</html>
