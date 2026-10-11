<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case REAUTHENTICATE = 'reauthenticate';
    case LOGIN = 'login';
    case CHANGE_PHONE = 'change_phone';
}
