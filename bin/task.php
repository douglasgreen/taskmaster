#!/usr/bin/env php
<?php

use DouglasGreen\OptParser\OptParser;
use DouglasGreen\TaskMaster\TaskFile;
use DouglasGreen\TaskMaster\TaskProcessor;
use DouglasGreen\TaskMaster\TaskStorage;

require_once __DIR__ . '/../vendor/autoload.php';

$optParser = new OptParser('Task Manager', 'Command-line version of task manager');

// Add commands.
$optParser
    ->addCommand(['process'], 'Process tasks and send emails')
    ->addCommand(['add'], 'Add a new task')
    ->addCommand(['search'], 'Search for tasks');

// Add params for add command.
$optParser
    ->addTerm('name', 'STRING', 'Task name')
    ->addParam(['url'], 'URL', 'URL for documentation or action')
    ->addParam(['recur'], 'BOOL', 'Recurring?')
    ->addParam(['start'], 'DATE', 'Recur start date')
    ->addParam(['end'], 'DATE', 'Recur end date')
    ->addParam(['year'], 'STRING', 'Days of year')
    ->addParam(['month'], 'STRING', 'Days of month')
    ->addParam(['week'], 'STRING', 'Days of week')
    ->addParam(['time'], 'STRING', 'Times of day');

// Add params for search command.
$optParser->addTerm('term', 'STRING', 'Term to search form');

// Add usage for add command.
$optParser->addUsage('add', [
    'name',
    'url',
    'recur',
    'start',
    'end',
    'year',
    'month',
    'week',
    'time',
]);

// Add usage for search command.
$optParser->addUsage('search', ['term']);

// Add usage with no arguments.
$optParser->addUsage('process', []);

$input = $optParser->parse();

$command = $input->getCommand();

$filename = __DIR__ . '/../assets/data/tasks.csv';

$configFile = __DIR__ . '/../config/config.ini';
if (!file_exists($configFile)) {
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

switch ($command) {
    case 'process':
        $taskStorage = new TaskStorage($pdo);
        $taskFile = new TaskFile($filename);
        $taskProcessor = new TaskProcessor($taskStorage, $taskFile);
        $taskProcessor->processTasks();
        break;

    case 'add':
        $taskFile = new TaskFile($filename);
        $taskFile->addTask(
            (string) $input->get('name'),
            (string) $input->get('url'),
            (bool) $input->get('recur'),
            (string) $input->get('start'),
            (string) $input->get('end'),
            (string) $input->get('year'),
            (string) $input->get('month'),
            (string) $input->get('week'),
            (string) $input->get('time'),
        );
        break;

    case 'search':
        $taskFile = new TaskFile($filename);
        $term = (string) $input->get('term');
        $tasks = $taskFile->search($term);
        foreach ($tasks as $task) {
            echo sprintf('Task Name: %s%s', $task->taskName, PHP_EOL);

            if ($task->taskUrl !== '') {
                echo sprintf('Task URL: %s%s', $task->taskUrl, PHP_EOL);
            }

            echo 'Recurring: ' . ($task->recurring ? 'Yes' : 'No') . PHP_EOL;

            if ($task->recurring) {
                if ($task->recurStart !== null) {
                    echo sprintf('Recur Start: %s%s', $task->recurStart, PHP_EOL);
                }

                if ($task->recurEnd !== null) {
                    echo sprintf('Recur End: %s%s', $task->recurEnd, PHP_EOL);
                }
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
