<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case PENDING_APPROVAL = 'pending_approval';
    case PENDING = 'pending';
    case INACTIVE = 'inactive';
    case DELETED = 'deleted';
}
