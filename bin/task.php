#!/usr/bin/env php
<?php

use DouglasGreen\OptParser\OptParser;
use DouglasGreen\TaskMaster\TaskDatabase;
use DouglasGreen\TaskMaster\TaskProcessor;
use DouglasGreen\TaskMaster\TaskStorage;

require_once __DIR__ . '/../vendor/autoload.php';

$optParser = new OptParser('Task Manager', 'Command-line version of task manager');

// Add commands.
$optParser
    ->addCommand(['process'], 'Process recurring tasks and insert into task list')
    ->addCommand(['add'], 'Add a new task')
    ->addCommand(['search'], 'Search for tasks');

// Add params for add command.
$optParser
    ->addTerm('name', 'STRING', 'Task name')
    ->addParam(['url'], 'URL', 'URL for documentation or action')
    ->addParam(['start'], 'DATE', 'Recur start date')
    ->addParam(['end'], 'DATE', 'Recur end date')
    ->addParam(['year'], 'STRING', 'Days of year')
    ->addParam(['month'], 'STRING', 'Days of month')
    ->addParam(['week'], 'STRING', 'Days of week')
    ->addParam(['time'], 'STRING', 'Times of day');

// Add params for search command.
$optParser->addTerm('term', 'STRING', 'Term to search form');

// Add usage for add command.
$optParser->addUsage('add', ['name', 'url', 'start', 'end', 'year', 'month', 'week', 'time']);

// Add usage for search command.
$optParser->addUsage('search', ['term']);

// Add usage with no arguments.
$optParser->addUsage('process', []);

$input = $optParser->parse();

$command = $input->getCommand();

$configFile = __DIR__ . '/../config/config.ini';
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
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $database);
$pdo = new PDO($dsn, $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

switch ($command) {
    case 'process':
        $taskStorage = new TaskStorage($pdo);
        $taskDatabase = new TaskDatabase($pdo);
        $taskProcessor = new TaskProcessor($taskStorage, $taskDatabase);
        $taskProcessor->processTasks();
        break;

    case 'add':
        $taskDatabase = new TaskDatabase($pdo);
        $taskDatabase->addTask(
            (string) $input->get('name'),
            (string) $input->get('url'),
            (string) $input->get('start'),
            (string) $input->get('end'),
            (string) $input->get('year'),
            (string) $input->get('month'),
            (string) $input->get('week'),
            (string) $input->get('time'),
        );
        echo "Task added successfully.\n";
        break;

    case 'search':
        $taskDatabase = new TaskDatabase($pdo);
        $term = (string) $input->get('term');
        $tasks = $taskDatabase->search($term);
        foreach ($tasks as $task) {
            echo sprintf('Task Name: %s%s', $task->taskName, PHP_EOL);

            if ($task->taskUrl !== '') {
                echo sprintf('Task URL: %s%s', $task->taskUrl, PHP_EOL);
            }

            if ($task->recurStart !== null) {
                echo sprintf('Recur Start: %s%s', $task->recurStart, PHP_EOL);
            }

            if ($task->recurEnd !== null) {
                echo sprintf('Recur End: %s%s', $task->recurEnd, PHP_EOL);
            }

            if ($task->daysOfYear !== []) {
                echo 'Days of Year: ' . implode(', ', $task->daysOfYear) . PHP_EOL;
            }

            if ($task->daysOfMonth !== []) {
                echo 'Days of Month: ' . implode(', ', $task->daysOfMonth) . PHP_EOL;
            }

            if ($task->daysOfWeek !== []) {
                echo 'Days of Week: ' . implode(', ', $task->getWeekdayNames()) . PHP_EOL;
            }

            if ($task->timesOfDay !== []) {
                echo 'Times of Day: ' . implode(', ', $task->timesOfDay) . PHP_EOL;
            }

            if ($task->lastTimeReminded !== 0) {
                echo 'Last Date Reminded: ' .
                    date('Y-m-d H:i:s', $task->lastTimeReminded) .
                    PHP_EOL;
            }

            echo '---------------------------------------' . PHP_EOL;
        }

        break;
}
