<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class MessageReceivedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [];
    }
}
