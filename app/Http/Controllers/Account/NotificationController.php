<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        // Ouvrir la page Notifications = les avoir vues : la pastille rouge
        // disparaît. Les nouvelles restent surlignées sur cette page (la liste
        // est chargée avant), puis apparaissent comme lues la fois suivante.
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('account.notifications.index', compact('notifications'));
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marquées comme lues.');
    }
}
