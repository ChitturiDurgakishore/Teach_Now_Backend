<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    //Get notifications for authenticated user

    public function getNotifications(Request $request)
    {
        try {

            // detect user
            if (Auth::guard('employer')->check()) {
                $type = 'employer';
                $id = Auth::guard('employer')->id();
            } elseif (Auth::guard('employer_user')->check()) {
                $type = 'recruiter';
                $id = Auth::guard('employer_user')->id();
            } else {
                $type = 'job_seeker';
                $id = Auth::id();
            }

            $notifications = Notification::where('notifiable_type', $type)
                ->where('notifiable_id', $id)
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mark notification as read
    public function markAsRead($id)
    {
        try {

            $notification = Notification::find($id);

            if (!$notification) {
                return response()->json([
                    'status' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->update(['is_read' => true]);

            return response()->json([
                'status' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Mark all notifications as read
    public function markAllAsRead(Request $request)
    {
        try {

            if (Auth::guard('employer')->check()) {
                $type = 'employer';
                $id = Auth::guard('employer')->id();
            } elseif (Auth::guard('employer_user')->check()) {
                $type = 'recruiter';
                $id = Auth::guard('employer_user')->id();
            } else {
                $type = 'job_seeker';
                $id = Auth::id();
            }

            Notification::where('notifiable_type', $type)
                ->where('notifiable_id', $id)
                ->update(['is_read' => true]);

            return response()->json([
                'status' => true,
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
