<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => request()->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function read(Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === request()->user()->id, 403);
        $notification->forceFill(['read' => true])->save();

        return response()->json(['data' => $notification]);
    }

    public function readAll(): JsonResponse
    {
        request()->user()->notifications()->where('read', false)->update(['read' => true]);

        return response()->json(['message' => 'Notifiche lette.']);
    }
}
