#!/usr/bin/env php
<?php

declare(strict_types=1);

use DouglasGreen\TaskMaster\ReminderEmail;
use DouglasGreen\TaskMaster\TaskFile;
use DouglasGreen\TaskMaster\TaskProcessor;

require_once __DIR__ . '/../vendor/autoload.php';

// @todo Make parameter
date_default_timezone_set('America/New_York');

// Define the CSV filename and headers
$filename = __DIR__ . '/../assets/data/tasks.csv';
$headers = [
    'Task name', 'Done?', 'Recurring?', 'Recur start', 'Recur end', 'Days of year',
    'Days of week', 'Days of month', 'Times of day', 'Last date reminded',
];

$reminderEmail = new ReminderEmail();
$taskFile = new TaskFile($filename, $headers);
$taskProcessor = new TaskProcessor($reminderEmail, $taskFile);
$taskProcessor->processTasks();
