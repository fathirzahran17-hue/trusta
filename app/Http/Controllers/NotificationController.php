<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $items = NotificationService::forUser($user);
        $readAt = $user->notifications_read_at;

        return view('notifications.index', compact('items', 'readAt'));
    }

    public function markRead()
    {
        $user = auth()->user();
        $user->forceFill(['notifications_read_at' => now()])->save();

        return request()->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
