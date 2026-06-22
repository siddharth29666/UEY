<?php

namespace App\Enums;

enum UserRole: string
{
    case RIDER = 'rider';
    case DRIVER = 'driver';
    case ADMIN = 'admin';
}
