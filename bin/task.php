#!/usr/bin/env php
<?php

declare(strict_types=1);

use DouglasGreen\OptParser\OptParser;
use DouglasGreen\TaskMaster\Infrastructure\Persistence\RecurringTaskRepository;
use DouglasGreen\TaskMaster\Infrastructure\Persistence\TaskGroupRepository;
use DouglasGreen\TaskMaster\Infrastructure\Persistence\TaskRepository;
use DouglasGreen\TaskMaster\TaskProcessor;

$optParser = new OptParser('Task Manager', 'Command-line version of task manager');
$optParser
    ->addCommand(['process'], 'Process recurring tasks and insert into task list')
    ->addCommand(['add'], 'Add a new task')
    ->addCommand(['search'], 'Search for tasks');
$optParser
    ->addTerm('name', 'STRING', 'Task name')
    ->addParam(['url'], 'URL', 'URL for documentation or action')
    ->addParam(['start'], 'DATE', 'Recur start date')
    ->addParam(['end'], 'DATE', 'Recur end date')
    ->addParam(['year'], 'STRING', 'Days of year')
    ->addParam(['month'], 'STRING', 'Days of month')
    ->addParam(['week'], 'STRING', 'Days of week')
    ->addParam(['time'], 'STRING', 'Times of day');
$optParser->addTerm('term', 'STRING', 'Term to search form');
$optParser->addUsage('add', ['name', 'url', 'start', 'end', 'year', 'month', 'week', 'time']);
$optParser->addUsage('search', ['term']);
$optParser->addUsage('process', []);

$input = $optParser->parse();
$command = $input->getCommand();

['pdo' => $pdo] = require __DIR__ . '/../bootstrap.php';

$recurringTaskRepo = new RecurringTaskRepository($pdo);
$taskRepo = new TaskRepository($pdo);
$groupRepo = new TaskGroupRepository($pdo);

match ($command) {
    'process' => (function () use ($recurringTaskRepo, $taskRepo, $groupRepo): void {
        $taskProcessor = new TaskProcessor($recurringTaskRepo, $taskRepo, $groupRepo);
        $taskProcessor->processTasks();
    })(),
    'add' => (function () use ($recurringTaskRepo, $input): void {
        $recurringTaskRepo->insert(
            (string) $input->get('name'),
            (string) $input->get('url'),
            (string) $input->get('start') ?: null,
            (string) $input->get('end') ?: null,
            (string) $input->get('year') ?: null,
            (string) $input->get('month') ?: null,
            (string) $input->get('week') ?: null,
            (string) $input->get('time') ?: null,
        );
        echo "Task added successfully.\n";
    })(),
    'search' => (function () use ($recurringTaskRepo, $input): void {
        $term = (string) $input->get('term');
        $rows = $recurringTaskRepo->search($term);
        foreach ($rows as $row) {
            echo sprintf('Task Name: %s%s', $row['title'], PHP_EOL);
            if ($row['details'] !== '') {
                echo sprintf('Task URL: %s%s', $row['details'], PHP_EOL);
            }
            if ($row['recur_start'] !== null) {
                echo sprintf('Recur Start: %s%s', $row['recur_start'], PHP_EOL);
            }
            if ($row['recur_end'] !== null) {
                echo sprintf('Recur End: %s%s', $row['recur_end'], PHP_EOL);
            }
            if ($row['days_of_year'] !== null) {
                echo 'Days of Year: ' . str_replace('|', ', ', $row['days_of_year']) . PHP_EOL;
            }
            if ($row['days_of_month'] !== null) {
                echo 'Days of Month: ' . str_replace('|', ', ', $row['days_of_month']) . PHP_EOL;
            }
            if ($row['days_of_week'] !== null) {
                echo 'Days of Week: ' . str_replace('|', ', ', $row['days_of_week']) . PHP_EOL;
            }
            if ($row['time_of_day'] !== null) {
                echo 'Times of Day: ' . str_replace('|', ', ', $row['time_of_day']) . PHP_EOL;
            }
            if ($row['last_reminded_at'] !== null) {
                echo 'Last Date Reminded: ' . $row['last_reminded_at'] . PHP_EOL;
            }
            echo '---------------------------------------' . PHP_EOL;
        }
    })(),
    default => throw new InvalidArgumentException('Unknown command: ' . $command),
};
