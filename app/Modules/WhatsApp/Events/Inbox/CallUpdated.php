<?php

namespace App\Modules\WhatsApp\Events\Inbox;

use App\Models\WhatsAppCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppCall $call
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("account.{$this->call->account_id}.whatsapp.inbox"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'whatsapp.call.updated';
    }

    public function broadcastWith(): array
    {
        $call = $this->call->fresh('agent:id,name') ?? $this->call;

        return [
            'call' => [
                'id' => $call->id,
                'whatsapp_connection_id' => $call->whatsapp_connection_id,
                'direction' => $call->direction,
                'phone_number' => $call->phone_number,
                'contact_name' => $call->contact_name,
                'provider' => $call->provider,
                'provider_call_id' => $call->provider_call_id,
                'status' => $call->status,
                'route_mode' => $call->route_mode,
                'routed_to' => $call->routed_to,
                'duration_seconds' => $call->duration_seconds,
                'summary' => $call->summary,
                'metadata' => $call->metadata ?? [],
                'agent' => $call->agent ? [
                    'id' => $call->agent->id,
                    'name' => $call->agent->name,
                ] : null,
                'created_at' => $call->created_at?->toIso8601String(),
                'started_at' => $call->started_at?->toIso8601String(),
                'ended_at' => $call->ended_at?->toIso8601String(),
                'updated_at' => $call->updated_at?->toIso8601String(),
            ],
        ];
    }
}
