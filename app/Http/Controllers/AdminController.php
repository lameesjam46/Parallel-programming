<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Notifications\SystemUpdateNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;

class AdminController extends Controller
{




public function sendNotificationToAll(Request $request)
{
    $users = User::all();
    
    $isIdealMode = $request->query('mode') === 'ideal';

    if ($isIdealMode) {

        foreach ($users as $user) {
            $user->notify(new SystemUpdateNotification());
        }

        return response()->json([
            'message' => 'تم إرسال الإشعارات '
        ], 202);
    } else {
        foreach ($users as $user) {

            $user->notifyNow(new SystemUpdateNotification());
        }

        return response()->json([
            'message' => 'تم إرسال الإشعارات '
        ], 200);
    }
}
}
