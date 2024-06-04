#!/usr/bin/env php
<?php

declare(strict_types=1);

use DouglasGreen\OptParser\OptParser;
use DouglasGreen\TaskMaster\TaskFile;

require_once __DIR__ . '/../vendor/autoload.php';

$optParser = new OptParser('Task Manager', 'Add tasks to list');

$optParser->addParam(['name'], 'STRING', 'Task name')
    ->addParam(['recurring'], 'BOOL', 'Recurring?')
    ->addParam(['recur_start'], 'DATE', 'Recur start date')
    ->addParam(['recur_end'], 'END', 'Recur end date')
    ->addParam(['days_of_year'], '', 'Recur end date')
    ->addUsageAll();

$input = $optParser->parse();

$email = $input->get('email');
if ($email === null) {
    die('Email is required' . PHP_EOL);
}

// Define the CSV filename and headers
$filename = __DIR__ . '/../assets/data/tasks.csv';
$headers = [
    'Task name', 'Done?', 'Recurring?', 'Recur start', 'Recur end', 'Days of year',
    'Days of week', 'Days of month', 'Times of day', 'Last date reminded',
];

$reminderEmail = new ReminderEmail((string) $email);
$taskFile = new TaskFile($filename, $headers);
$taskProcessor = new TaskProcessor($reminderEmail, $taskFile);
$taskProcessor->processTasks();

