#!/usr/bin/env php
<?php

declare(strict_types=1);

use DouglasGreen\OptParser\OptParser;
use DouglasGreen\TaskMaster\TaskFile;

require_once __DIR__ . '/../vendor/autoload.php';

$optParser = new OptParser('Task Manager', 'Add tasks to list');

$optParser->addParam(['name'], 'STRING', 'Task name')
    ->addParam(['recur'], 'BOOL', 'Recurring?')
    ->addParam(['start'], 'DATE', 'Recur start date')
    ->addParam(['end'], 'DATE', 'Recur end date')
    ->addParam(['year'], 'STRING', 'Days of year')
    ->addParam(['month'], 'STRING', 'Days of month')
    ->addParam(['week'], 'STRING', 'Days of week')
    ->addParam(['day'], 'STRING', 'Times of day')
    ->addUsageAll();

$input = $optParser->parse();

$filename = __DIR__ . '/../assets/data/tasks.csv';

$taskFile = new TaskFile($filename);
$taskFile->addTask(
    (string) $input->get('name'),
    (bool) $input->get('recur'),
    (string) $input->get('start'),
    (string) $input->get('end'),
    (string) $input->get('year'),
    (string) $input->get('month'),
    (string) $input->get('week'),
    (string) $input->get('day')
);
