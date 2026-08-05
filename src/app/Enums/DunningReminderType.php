<?php

namespace App\Enums;

enum DunningReminderType: string
{
    case Overdue = 'overdue';
    case Reminder1 = 'reminder_1';
    case Reminder2 = 'reminder_2';
}
