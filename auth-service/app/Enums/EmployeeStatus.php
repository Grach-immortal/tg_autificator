<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'активный';
    case Fired = 'уволен';
}
