<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case LOGIN = 'login';
    case CHANGE_PHONE = 'change_phone';
}
