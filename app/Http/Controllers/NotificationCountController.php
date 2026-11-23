<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint JSON ringan untuk AJAX short polling jumlah notifikasi belum dibaca.
 * Dipakai oleh navbar customer, topbar admin, dan sidebar panel staf.
 */
class NotificationCountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Staf panel admin tanpa izin notifications.view tidak menerima notifikasi di panelnya.
        if ($user->isStaff() && ! $user->isMechanic() && ! $user->hasPermission('notifications.view')) {
            return response()->json([
                'unread' => 0,
                'unread_label' => '',
            ]);
        }

        $unread = $user->unreadNotifications()->count();

        return response()->json([
            'unread' => $unread,
            'unread_label' => $unread > 9 ? '9+' : ($unread > 0 ? (string) $unread : ''),
        ]);
    }
}
