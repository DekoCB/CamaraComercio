<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marks every currently-unread notification as read by this user.
     * A shared feed (every user sees the same rows), so this only ever
     * appends the current user's id to each row's read_by list — it
     * never affects what other users see as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        Notification::query()
            ->whereJsonDoesntContain('read_by', $userId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->each(function (Notification $notification) use ($userId) {
                $notification->update(['read_by' => [...$notification->read_by, $userId]]);
            });

        return back();
    }
}
