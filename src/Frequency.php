<?php

declare(strict_types=1);

namespace DouglasGreen\TaskMaster;

enum Frequency: string
{
    case Daily = 'Daily';
    case Weekdays = 'Weekday';
    case Weekends = 'Weekend';
    case Weekly = 'Weekly';
    case Monthly = 'Monthly';
}
