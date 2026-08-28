<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Notification\NotificationResource;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection($this->notificationService->paginate($request->user()));
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $readNotification = $this->notificationService->markAsRead($request->user(), $notification);

        return response()->json([
            'data' => (new NotificationResource($readNotification))->resolve($request),
        ]);
    }
}
