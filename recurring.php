<?php

require_once __DIR__ . '/vendor/autoload.php';

use DouglasGreen\TaskMaster\TaskDatabase;
use DouglasGreen\Utility\Regex\Regex;

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

// Initialize logic helpers
$taskDatabase = new TaskDatabase($pdo);

// Helper to format the schedule string for display
function formatSchedule($task) {
    $parts = [];
    
    // Dates
    if (!empty($task['days_of_week'])) {
        $map = [
            1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 
            5 => 'Fri', 6 => 'Sat', 7 => 'Sun'
        ];
        // Simple parser for display purposes
        if ($task['days_of_week'] === '*') return 'Daily';
        
        $days = [];
        $tokens = explode('|', $task['days_of_week']);
        foreach ($tokens as $token) {
            if (strpos($token, '-') !== false) {
                [$s, $e] = explode('-', $token);
                $days[] = ($map[$s] ?? $s) . '-' . ($map[$e] ?? $e);
            } else {
                $days[] = $map[$token] ?? $token;
            }
        }
        $parts[] = 'Every ' . implode(', ', $days);
    } elseif (!empty($task['days_of_month'])) {
        $parts[] = 'Monthly on day(s): ' . str_replace('|', ', ', $task['days_of_month']);
    } elseif (!empty($task['days_of_year'])) {
        $parts[] = 'Yearly on: ' . str_replace('|', ', ', $task['days_of_year']);
    } else {
        $parts[] = 'Daily';
    }

    // Times
    if (!empty($task['time_of_day'])) {
        $parts[] = 'at ' . str_replace('|', ', ', $task['time_of_day']);
    }

    // Ranges
    if (!empty($task['recur_start'])) {
        $parts[] = 'starting ' . $task['recur_start'];
    }
    if (!empty($task['recur_end'])) {
        $parts[] = 'until ' . $task['recur_end'];
    }

    return implode(' ', $parts);
}

// AJAX Handler
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    try {
        if ($_GET['ajax'] === 'add_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Extract raw inputs
            $name = trim($_POST['name']);
            $url = trim($_POST['url']); // Mapped to details in DB via TaskDatabase
            $start = $_POST['recur_start'] ?: '';
            $end = $_POST['recur_end'] ?: '';
            $time = trim($_POST['time_of_day']); // e.g. "09:00|14:00"

            // Frequency Handling
            $freq = $_POST['frequency_type'];
            $daysOfWeek = '';
            $daysOfMonth = '';
            $daysOfYear = '';

            if ($freq === 'weekly') {
                // Convert array of checkbox values [1, 3, 5] to "1|3|5"
                if (isset($_POST['days_of_week']) && is_array($_POST['days_of_week'])) {
                    $daysOfWeek = implode('|', $_POST['days_of_week']);
                }
            } elseif ($freq === 'monthly') {
                $daysOfMonth = trim($_POST['days_of_month']);
            } elseif ($freq === 'yearly') {
                $daysOfYear = trim($_POST['days_of_year']);
            }
            // 'daily' implies empty day fields, which Task class handles as daily if times exist

            if (empty($name)) {
                throw new Exception('Task name is required');
            }

            // Use the existing class to add (handles validation and piping)
            $taskDatabase->addTask(
                $name,
                $url,
                $start,
                $end,
                $daysOfYear,
                $daysOfMonth,
                $daysOfWeek,
                $time
            );

            echo json_encode(['success' => true, 'message' => 'Recurring task added successfully']);
            exit;
        }

        if ($_GET['ajax'] === 'edit_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)$_POST['task_id'];
            $name = trim($_POST['name']);
            $details = trim($_POST['url']); 
            $start = $_POST['recur_start'] ?: null;
            $end = $_POST['recur_end'] ?: null;
            $time = trim($_POST['time_of_day']);

            $freq = $_POST['frequency_type'];
            $daysOfWeek = null;
            $daysOfMonth = null;
            $daysOfYear = null;

            if ($freq === 'weekly') {
                if (isset($_POST['days_of_week']) && is_array($_POST['days_of_week'])) {
                    $daysOfWeek = implode('|', $_POST['days_of_week']);
                }
            } elseif ($freq === 'monthly') {
                $daysOfMonth = trim($_POST['days_of_month']) ?: null;
            } elseif ($freq === 'yearly') {
                $daysOfYear = trim($_POST['days_of_year']) ?: null;
            }

            if (empty($name)) throw new Exception('Name is required');

            // Manual update since TaskDatabase doesn't support full updates
            $sql = "UPDATE recurring_tasks SET 
                    title = ?, 
                    details = ?, 
                    recur_start = ?, 
                    recur_end = ?, 
                    days_of_week = ?, 
                    days_of_month = ?, 
                    days_of_year = ?, 
                    time_of_day = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, $details, $start, $end, 
                $daysOfWeek, $daysOfMonth, $daysOfYear, $time, 
                $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
            exit;
        }

        if ($_GET['ajax'] === 'delete_task' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)$_POST['task_id'];
            $stmt = $pdo->prepare("DELETE FROM recurring_tasks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
            exit;
        }

        if ($_GET['ajax'] === 'get_task' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $id = (int)$_GET['task_id'];
            $stmt = $pdo->prepare("SELECT * FROM recurring_tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                // Determine frequency for UI
                $freq = 'daily';
                if (!empty($task['days_of_week'])) $freq = 'weekly';
                if (!empty($task['days_of_month'])) $freq = 'monthly';
                if (!empty($task['days_of_year'])) $freq = 'yearly';

                // Explode weeks for checkboxes
                $weekArr = [];
                if ($task['days_of_week']) {
                    $parts = explode('|', $task['days_of_week']);
                    foreach ($parts as $p) {
                        if (strpos($p, '-') !== false) {
                            [$s, $e] = explode('-', $p);
                            for ($i=$s; $i<=$e; $i++) $weekArr[] = $i;
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
            exit;
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch Data for View
$search_query = $_GET['search'] ?? '';
$is_searching = !empty($search_query);

if ($is_searching) {
    $tasks = $taskDatabase->search($search_query);
    // search returns Task objects, convert to array for consistent view logic
    $viewTasks = [];
    foreach ($tasks as $t) {
        $viewTasks[] = [
            'id' => $t->dbId,
            'title' => $t->taskName,
            'details' => $t->taskUrl,
            'recur_start' => $t->recurStart,
            'recur_end' => $t->recurEnd,
            'days_of_week' => implode('|', $t->daysOfWeek),
            'days_of_month' => implode('|', $t->daysOfMonth),
            'days_of_year' => implode('|', $t->daysOfYear),
            'time_of_day' => implode('|', $t->timesOfDay),
            'last_reminded_at' => $t->lastTimeReminded ? date('Y-m-d H:i:s', $t->lastTimeReminded) : null
        ];
    }
} else {
    // Use raw query for initial load to match index.php style direct data handling
    // (or we could use $taskDatabase->loadTasks())
    $stmt = $pdo->query("SELECT * FROM recurring_tasks ORDER BY title ASC");
    $viewTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Tasks - Task Manager</title>
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
        .skip-link:focus { top: 0; }

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
        .header h1 { font-size: 1.75rem; margin: 0; }

        .main-container {
            height: calc(100vh - var(--header-height));
            overflow: hidden;
        }

        .left-panel {
            background: white;
            height: 100%;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            width: var(--sidebar-width);
            padding-top: 20px;
        }

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

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

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

        .table thead th {
            background: #343a40;
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }
        .table td { vertical-align: middle; padding: 12px 15px; }

        .nav-link-custom {
            display: block;
            padding: 12px 20px;
            color: #495057;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        .nav-link-custom:hover {
            background-color: #f0f3ff;
            color: #764ba2;
        }
        .nav-link-custom.active {
            background: #f0f3ff;
            border-left-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }

        .toast-container {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 1050;
        }

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
        .spinner-overlay.show { display: flex; }

        /* Mobile specifics omitted for brevity, relying on Bootstrap responsive utilities */
    </style>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <header class="header">
        <h1>Task Manager <span class="fs-6 opacity-75">Recurring</span></h1>
        <a href="index.php" class="btn btn-outline-light btn-sm d-lg-none">Tasks</a>
    </header>

    <div class="toast-container" id="toastContainer"></div>

    <div class="container-fluid main-container">
        <div class="row h-100">
            <!-- Sidebar -->
            <aside class="left-panel d-none d-lg-block">
                <div class="mb-4">
                    <h5 class="px-4 text-muted mb-3">Navigation</h5>
                    <nav>
                        <a href="index.php" class="nav-link-custom">
                            <i class="bi bi-check2-square me-2"></i> One-time Tasks
                        </a>
                        <a href="recurring.php" class="nav-link-custom active">
                            <i class="bi bi-arrow-repeat me-2"></i> Recurring Tasks
                        </a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="right-panel" id="main-content">
                <div class="right-panel-content">
                    
                    <!-- Breadcrumbs / Search Header -->
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <h2 class="mb-0">Recurring Tasks</h2>
                            <div class="d-flex gap-2">
                                <form method="get" class="d-flex gap-2">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search recurring..." 
                                           value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" class="btn btn-gradient"><i class="bi bi-search"></i></button>
                                    <?php if($is_searching): ?>
                                        <a href="recurring.php" class="btn btn-secondary">Clear</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($viewTasks)): ?>
                        <div class="text-center py-5 card">
                            <div class="card-body">
                                <i class="bi bi-arrow-repeat text-muted" style="font-size: 3rem;"></i>
                                <h3 class="mt-3 text-muted">No recurring tasks found</h3>
                                <p class="text-muted">Tasks added here will automatically generate reminders based on their schedule.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px">Delete</th>
                                            <th style="width: 80px">Edit</th>
                                            <th>Name</th>
                                            <th>Details / URL</th>
                                            <th>Schedule</th>
                                            <th>Last Reminded</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($viewTasks as $task): ?>
                                            <tr data-task-id="<?php echo $task['id']; ?>">
                                                <td>
                                                    <button class="btn btn-sm btn-danger delete-task-btn" 
                                                            data-task-id="<?php echo $task['id']; ?>"
                                                            data-task-name="<?php echo htmlspecialchars($task['title']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary edit-task-btn" 
                                                            data-task-id="<?php echo $task['id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td>
                                                    <?php 
                                                        $details = $task['details'];
                                                        if (filter_var($details, FILTER_VALIDATE_URL)) {
                                                            echo '<a href="'.htmlspecialchars($details).'" target="_blank">Link <i class="bi bi-box-arrow-up-right small"></i></a>';
                                                        } else {
                                                            echo htmlspecialchars(substr($details, 0, 50)) . (strlen($details)>50 ? '...' : '');
                                                        }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(formatSchedule($task)); ?></td>
                                                <td class="text-muted small">
                                                    <?php echo $task['last_reminded_at'] ? $task['last_reminded_at'] : 'Never'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="taskModalLabel">Recurring Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="taskForm">
                    <div class="modal-body">
                        <input type="hidden" id="taskId" name="task_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Task Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="taskName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Details / URL</label>
                                <input type="text" class="form-control" name="url" id="taskUrl" placeholder="http://...">
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Schedule</h6>

                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="frequency_type" id="freqDaily" value="daily" checked>
                                <label class="btn btn-outline-primary" for="freqDaily">Daily</label>

                                <input type="radio" class="btn-check" name="frequency_type" id="freqWeekly" value="weekly">
                                <label class="btn btn-outline-primary" for="freqWeekly">Weekly</label>

                                <input type="radio" class="btn-check" name="frequency_type" id="freqMonthly" value="monthly">
                                <label class="btn btn-outline-primary" for="freqMonthly">Monthly</label>

                                <input type="radio" class="btn-check" name="frequency_type" id="freqYearly" value="yearly">
                                <label class="btn btn-outline-primary" for="freqYearly">Yearly</label>
                            </div>
                        </div>

                        <!-- Weekly Options -->
                        <div id="weeklyOptions" class="mb-3 p-3 bg-light rounded d-none">
                            <label class="form-label d-block">Days of Week</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php 
                                $days = [1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri', 6=>'Sat', 7=>'Sun'];
                                foreach($days as $num => $name): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="<?php echo $num; ?>" id="day<?php echo $num; ?>">
                                        <label class="form-check-label" for="day<?php echo $num; ?>"><?php echo $name; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Monthly Options -->
                        <div id="monthlyOptions" class="mb-3 p-3 bg-light rounded d-none">
                            <label class="form-label">Days of Month (e.g., "1, 15" or "1-5")</label>
                            <input type="text" class="form-control" name="days_of_month" id="daysOfMonth" placeholder="1|15">
                        </div>

                        <!-- Yearly Options -->
                        <div id="yearlyOptions" class="mb-3 p-3 bg-light rounded d-none">
                            <label class="form-label">Days of Year (MM-DD, e.g., "12-25")</label>
                            <input type="text" class="form-control" name="days_of_year" id="daysOfYear" placeholder="12-25">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Time of Day</label>
                                <input type="text" class="form-control" name="time_of_day" id="timeOfDay" placeholder="09:00">
                                <div class="form-text">HH:MM (24h). Separate multiple with |</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date (Opt)</label>
                                <input type="date" class="form-control" name="recur_start" id="recurStart">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date (Opt)</label>
                                <input type="date" class="form-control" name="recur_end" id="recurEnd">
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient" id="saveBtn">Save Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete "<strong id="deleteTaskName"></strong>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Add Button -->
    <button class="btn btn-gradient btn-add-task" id="addTaskBtn" title="Add Recurring Task">
        <i class="bi bi-plus-lg"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toast Logic
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const id = 'toast-' + Date.now();
            const bg = type === 'success' ? 'bg-success' : 'bg-danger';
            
            const html = `
                <div class="toast align-items-center text-white ${bg} border-0" id="${id}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>`;
            
            container.insertAdjacentHTML('beforeend', html);
            const el = document.getElementById(id);
            const toast = new bootstrap.Toast(el, { autohide: true, delay: 4000 });
            toast.show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        }

        const showLoading = () => document.getElementById('loadingSpinner').classList.add('show');
        const hideLoading = () => document.getElementById('loadingSpinner').classList.remove('show');

        // Modal & Form Logic
        const taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
        const form = document.getElementById('taskForm');
        const freqRadios = document.getElementsByName('frequency_type');
        
        // Toggle Frequency Panels
        function updateFrequencyView() {
            const val = document.querySelector('input[name="frequency_type"]:checked').value;
            document.getElementById('weeklyOptions').classList.add('d-none');
            document.getElementById('monthlyOptions').classList.add('d-none');
            document.getElementById('yearlyOptions').classList.add('d-none');

            if (val === 'weekly') document.getElementById('weeklyOptions').classList.remove('d-none');
            if (val === 'monthly') document.getElementById('monthlyOptions').classList.remove('d-none');
            if (val === 'yearly') document.getElementById('yearlyOptions').classList.remove('d-none');
        }

        freqRadios.forEach(r => r.addEventListener('change', updateFrequencyView));

        // Open Modal (Add)
        document.getElementById('addTaskBtn').addEventListener('click', () => {
            form.reset();
            document.getElementById('taskId').value = '';
            document.getElementById('taskModalLabel').textContent = 'Add Recurring Task';
            document.getElementById('saveBtn').textContent = 'Add Task';
            document.getElementById('freqDaily').checked = true;
            updateFrequencyView();
            taskModal.show();
        });

        // Open Modal (Edit)
        document.addEventListener('click', e => {
            const btn = e.target.closest('.edit-task-btn');
            if (btn) {
                const id = btn.dataset.taskId;
                showLoading();
                fetch(`?ajax=get_task&task_id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        hideLoading();
                        if(data.success) {
                            const t = data.task;
                            document.getElementById('taskId').value = t.id;
                            document.getElementById('taskName').value = t.title;
                            document.getElementById('taskUrl').value = t.details;
                            document.getElementById('recurStart').value = t.recur_start;
                            document.getElementById('recurEnd').value = t.recur_end;
                            document.getElementById('timeOfDay').value = t.time_of_day;

                            // Set Frequency
                            document.querySelector(`input[name="frequency_type"][value="${t.frequency_type}"]`).checked = true;
                            updateFrequencyView();

                            // Set Specifics
                            if (t.frequency_type === 'weekly' && t.days_of_week_arr) {
                                t.days_of_week_arr.forEach(d => {
                                    const cb = document.getElementById('day'+d);
                                    if(cb) cb.checked = true;
                                });
                            }
                            if (t.frequency_type === 'monthly') {
                                document.getElementById('daysOfMonth').value = t.days_of_month;
                            }
                            if (t.frequency_type === 'yearly') {
                                document.getElementById('daysOfYear').value = t.days_of_year;
                            }

                            document.getElementById('taskModalLabel').textContent = 'Edit Recurring Task';
                            document.getElementById('saveBtn').textContent = 'Update Task';
                            taskModal.show();
                        } else {
                            showToast(data.message, 'error');
                        }
                    })
                    .catch(err => { hideLoading(); console.error(err); });
            }
        });

        // Save
        form.addEventListener('submit', e => {
            e.preventDefault();
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const formData = new FormData(form);
            const id = document.getElementById('taskId').value;
            const action = id ? 'edit_task' : 'add_task';

            showLoading();
            fetch(`?ajax=${action}`, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        taskModal.hide();
                        showToast(data.message);
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(err => { hideLoading(); console.error(err); });
        });

        // Delete Logic
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        let deleteId = null;

        document.addEventListener('click', e => {
            const btn = e.target.closest('.delete-task-btn');
            if (btn) {
                deleteId = btn.dataset.taskId;
                document.getElementById('deleteTaskName').textContent = btn.dataset.taskName;
                deleteModal.show();
            }
        });

        document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
            if (!deleteId) return;
            const fd = new FormData();
            fd.append('task_id', deleteId);
            
            showLoading();
            fetch('?ajax=delete_task', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        deleteModal.hide();
                        showToast(data.message);
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(err => { hideLoading(); console.error(err); });
        });

    </script>
</body>
</html>
