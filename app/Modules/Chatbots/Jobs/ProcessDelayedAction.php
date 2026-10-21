<?php

namespace App\Modules\Chatbots\Jobs;

use App\Modules\Chatbots\Models\BotActionJob;
use App\Modules\Chatbots\Services\ActionExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDelayedAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $actionJobId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ActionExecutor $actionExecutor): void
    {
        $actionJob = BotActionJob::find($this->actionJobId);
        if (! $actionJob || $actionJob->status !== 'queued') {
            return;
        }

        $actionJob->update(['status' => 'running']);

        try {
            // Get execution and conversation
            $execution = $actionJob->execution;
            $node = $actionJob->node;
            $conversation = $execution->conversation;
            $connection = $conversation->connection;
            $inboundMessage = $conversation->messages()
                ->where('direction', 'inbound')
                ->latest()
                ->first();

            if (! $inboundMessage) {
                throw new \Exception('Inbound message not found');
            }

            // Create context
            $context = new \App\Modules\Chatbots\Services\BotContext(
                account: $execution->account,
                conversation: $conversation,
                inboundMessage: $inboundMessage,
                connection: $connection,
                metadata: ['execution_id' => $execution->id]
            );

            // Execute the delayed action node
            $result = $actionExecutor->execute($node, $context);

            $actionJob->update([
                'status' => $result['success'] ? 'done' : 'failed',
                'last_error' => $result['error'] ?? null]);
        } catch (\Exception $e) {
            $actionJob->update([
                'status' => 'failed',
                'last_error' => $e->getMessage()]);
        }
    }
}
