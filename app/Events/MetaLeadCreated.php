<?php

namespace App\Events;

use App\Models\AccountMetaLead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetaLeadCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AccountMetaLead $lead
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("account.{$this->lead->account_id}.whatsapp.inbox")];
    }

    public function broadcastAs(): string
    {
        return 'meta.lead.created';
    }

    public function broadcastWith(): array
    {
        return [
            'account_id' => $this->lead->account_id,
            'lead' => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'phone' => $this->lead->phone,
                'email' => $this->lead->email,
                'platform' => $this->lead->platform,
                'form_name' => $this->lead->form_name,
                'ad_name' => $this->lead->ad_name,
                'campaign_name' => $this->lead->campaign_name,
                'stage' => $this->lead->stage,
                'assignee_name' => $this->lead->assignee_name,
                'captured_at' => $this->lead->captured_at?->toIso8601String(),
            ],
        ];
    }
}
