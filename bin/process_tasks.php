#!/usr/bin/env php
<?php

declare(strict_types=1);

use DouglasGreen\OptParser\OptParser;
use DouglasGreen\TaskMaster\ReminderEmail;
use DouglasGreen\TaskMaster\TaskFile;
use DouglasGreen\TaskMaster\TaskProcessor;

require_once __DIR__ . '/../vendor/autoload.php';

$optParser = new OptParser('Task Manager', 'Command-line version of task manager');

$optParser->addParam(['email', 'e'], 'EMAIL', 'Your email address')
    ->addParam(['timezone', 't'], 'STRING', 'Your timezone')
    ->addUsageAll();

$input = $optParser->parse();

$timezone = $input->get('timezone');
if ($timezone !== null) {
    date_default_timezone_set((string) $timezone);
}

$email = $input->get('email');
if ($email === null) {
    die('Email is required' . PHP_EOL);
}

$filename = __DIR__ . '/../assets/data/tasks.csv';

$reminderEmail = new ReminderEmail((string) $email);
$taskFile = new TaskFile($filename);
$taskProcessor = new TaskProcessor($reminderEmail, $taskFile);
$taskProcessor->processTasks();
