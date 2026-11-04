<?php
namespace App\Enums;

enum NotificationChannel: string
{
    case Telegram = 'telegram';
    case Push = 'push';
    case Email = 'email';
}
