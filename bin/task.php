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

// Add params for process command.
$optParser
    ->addParam(['host'], 'STRING', 'Your database host')
    ->addParam(['port'], 'INT', 'Your database port')
    ->addParam(['database'], 'STRING', 'Your database name')
    ->addParam(['user'], 'STRING', 'Your database user')
    ->addParam(['password'], 'STRING', 'Your database password');

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

// Add usage for process command.
$optParser->addUsage('process', ['host', 'port', 'database', 'user', 'password']);

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

$input = $optParser->parse();

$command = $input->getCommand();

$filename = __DIR__ . '/../assets/data/tasks.csv';

switch ($command) {
    case 'process':
        $host = $input->get('host');
        $port = $input->get('port');
        $database = $input->get('database');
        $user = $input->get('user');
        $password = $input->get('password');

        if ($port === null) {
            $port = 3306;
        }

        if ($host === null || $database === null || $user === null || $password === null) {
            die("Missing arguments\n");
        }


        $taskStorage = new TaskStorage(
            (string) $host,
            (int) $port,
            (string) $database,
            (string) $user,
            (string) $password
        );
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
