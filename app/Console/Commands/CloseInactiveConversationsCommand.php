<?php

namespace App\Console\Commands;

use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Console\Command;

class CloseInactiveConversationsCommand extends Command
{
    protected $signature = 'conversations:auto-close {--limit=500}';

    protected $description = 'Close open WhatsApp conversations after the workspace inactivity window.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $closed = 0;

        WhatsAppConversation::query()
            ->with('account:id,auto_close_conversations_enabled,auto_close_after_hours')
            ->where('status', 'open')
            ->whereNotNull('last_message_at')
            ->whereHas('account', function ($query) {
                $query->where('auto_close_conversations_enabled', true)
                    ->where('auto_close_after_hours', '>', 0);
            })
            ->orderBy('last_message_at')
            ->limit($limit)
            ->get()
            ->each(function (WhatsAppConversation $conversation) use (&$closed) {
                $hours = max(1, (int) ($conversation->account?->auto_close_after_hours ?? 0));
                if (! $conversation->last_message_at?->lte(now()->subHours($hours))) {
                    return;
                }

                $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                $metadata['auto_closed_at'] = now()->toIso8601String();
                $metadata['auto_close_after_hours'] = $hours;

                $conversation->forceFill([
                    'status' => 'closed',
                    'metadata' => $metadata,
                ])->save();

                if (method_exists($conversation, 'auditEvents')) {
                    $conversation->auditEvents()->create([
                        'account_id' => $conversation->account_id,
                        'event_type' => 'conversation.auto_closed',
                        'actor_type' => 'system',
                        'summary' => "Conversation auto-closed after {$hours} inactive hour(s).",
                        'metadata' => ['auto_close_after_hours' => $hours],
                    ]);
                }

                event(new ConversationUpdated($conversation->fresh(['contact'])));
                $closed++;
            });

        $this->info("Closed {$closed} inactive conversation(s).");

        return self::SUCCESS;
    }
}
