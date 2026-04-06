<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster\Helper;

final class DateHelper
{
    public static function formatDueDate(?string $dueDateStr): string
    {
        if (empty($dueDateStr) || $dueDateStr === '0000-00-00') {
            return '';
        }
        $due = new \DateTime($dueDateStr);
        $now = new \DateTime();
        $now->setTime(0, 0, 0);
        $due->setTime(0, 0, 0);
        $interval = $now->diff($due);
        $daysFromNow = (int) ($interval->invert ? -$interval->days : $interval->days);
        $absDays = abs($daysFromNow);
        if ($absDays > 99) {
            return $dueDateStr;
        }
        if ($daysFromNow < 0) {
            return $absDays === 1 ? 'yesterday' : $absDays . ' days ago';
        }
        if ($daysFromNow === 0) {
            return 'today';
        }
        if ($daysFromNow === 1) {
            return 'tomorrow';
        }
        return 'in ' . $daysFromNow . ' days';
    }

    public static function isTaskDue(?string $dueDateStr): bool
    {
        if (empty($dueDateStr) || $dueDateStr === '0000-00-00') {
            return false;
        }
        $due = new \DateTime($dueDateStr);
        $now = new \DateTime();
        $due->setTime(0, 0, 0);
        $now->setTime(0, 0, 0);
        return $due <= $now;
    }
}
