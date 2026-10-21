<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AppNotification $notification) {}

    public function broadcastOn(): array
    {
        if ($this->notification->scope === 'platform') {
            return [new PrivateChannel('platform.notifications')];
        }

        return [new PrivateChannel("account.{$this->notification->account_id}.notifications")];
    }

    public function broadcastAs(): string
    {
        return 'app.notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'account_id' => $this->notification->account_id,
                'scope' => $this->notification->scope,
                'type' => $this->notification->type,
                'severity' => $this->notification->severity,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'action_url' => $this->notification->action_url,
                'data' => $this->notification->data,
                'read_at' => $this->notification->read_at?->toIso8601String(),
                'created_at' => $this->notification->created_at?->toIso8601String(),
                'updated_at' => $this->notification->updated_at?->toIso8601String(),
            ],
        ];
    }
}
