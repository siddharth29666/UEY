<?php

namespace App\Enums;

enum OtpType: string
{
    case REGISTER = 'register';
    case LOGIN = 'login';
    case PASSWORD_RESET = 'password_reset';
}
