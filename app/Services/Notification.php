<?php
namespace App\Services;

use App\Models\Notification as NotificationModel;

class Notification
{
    public function send($type, $userType, $userId, $title, $message, $data = [])
    {
        return NotificationModel::create([
            'type' => $type,
            'notifiable_type' => $userType,
            'notifiable_id' => $userId,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ]);
    }
}
