<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotExecution;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class BotRuntime
{
    protected const ACTION_TYPES = [
        'send_text',
        'send_template',
        'send_buttons',
        'send_list',
        'send_media',
        'send_flow',
        'assign_agent',
        'add_tag',
        'add_segment',
        'update_contact',
        'create_deal',
        'create_appointment',
        'sync_integration',
        'set_status',
        'set_priority',
        'handoff',
        'send_payment_link',
        'ai_agent_reply',
    ];

    public function __construct(
        protected TriggerEvaluator $triggerEvaluator,
        protected ConditionEvaluator $conditionEvaluator,
        protected ActionExecutor $actionExecutor
    ) {}

    /**
     * Some older flows or buggy saves may persist non-standard node types.
     * Normalize the execution category so the runtime can still traverse and execute.
     */
    protected function normalizeNodeType(BotNode $node): string
    {
        $type = (string) ($node->type ?? '');
        if (in_array($type, ['condition', 'action', 'delay', 'webhook'], true)) {
            return $type;
        }

        // Backward compatibility: older flows may persist the action type as node type.
        if (in_array($type, self::ACTION_TYPES, true)) {
            return 'action';
        }

        $config = is_array($node->config) ? $node->config : [];

        // If the config shape clearly matches a known node category, treat it as such.
        if (array_key_exists('action_type', $config)) {
            return 'action';
        }
        if (array_key_exists('seconds', $config)) {
            return 'delay';
        }
        if (array_key_exists('url', $config)) {
            return 'webhook';
        }
        if (array_key_exists('type', $config)) {
            return 'condition';
        }

        // Pure pass-through/unknown nodes still allow graph traversal.
        return 'passthrough';
    }

    /**
     * Pick the best starting node for graph execution.
     */
    protected function resolveStartNode($nodes, $edgesByFrom): ?BotNode
    {
        $explicitStart = $nodes->first(fn (BotNode $node) => ($node->config['is_start'] ?? false) === true);
        if ($explicitStart) {
            return $explicitStart;
        }

        // Prefer roots (no incoming edges) that can actually route/execute.
        $incomingNodeIds = $edgesByFrom
            ->flatten(1)
            ->pluck('to_node_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $incomingSet = array_flip($incomingNodeIds);

        $rootExecutable = $nodes
            ->sortBy('sort_order')
            ->first(function (BotNode $node) use ($incomingSet) {
                $nodeId = (int) $node->id;
                if (isset($incomingSet[$nodeId])) {
                    return false;
                }

                return in_array($this->normalizeNodeType($node), ['condition', 'action', 'delay', 'webhook'], true);
            });

        if ($rootExecutable) {
            return $rootExecutable;
        }

        // Fallback to first executable node.
        $firstExecutable = $nodes
            ->sortBy('sort_order')
            ->first(fn (BotNode $node) => in_array($this->normalizeNodeType($node), ['condition', 'action', 'delay', 'webhook'], true));

        if ($firstExecutable) {
            return $firstExecutable;
        }

        // Last fallback keeps traversal possible for malformed graphs.
        return $nodes->sortBy('sort_order')->first();
    }

    /**
     * Execute nodes in linear mode.
     */
    protected function executeLinear($nodes, BotContext $context, int $maxActions, int &$actionCount): array
    {
        $logs = [];
        $ordered = $nodes->sortBy('sort_order')->values();
        $messageSent = false;

        foreach ($ordered as $node) {
            if ($actionCount >= $maxActions) {
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => $node->type,
                    'result' => 'skipped',
                    'reason' => 'Max actions limit reached',
                ];
                break;
            }

            $normalized = $this->normalizeNodeType($node);

            if ($normalized === 'condition') {
                $passed = $this->conditionEvaluator->evaluate($node, $context);
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => 'condition',
                    'result' => $passed ? 'passed' : 'failed',
                ];
                if (! $passed) {
                    continue;
                }
            }

            if (in_array($normalized, ['action', 'delay', 'webhook'], true)) {
                if ($node->type !== $normalized) {
                    $node->type = $normalized;
                }
                if ($normalized === 'action' && $messageSent && $this->isMessageAction($node)) {
                    $logs[] = [
                        'node_id' => $node->id,
                        'type' => $normalized,
                        'result' => 'skipped',
                        'reason' => 'Only one customer-facing message is allowed per automation execution',
                    ];

                    continue;
                }

                $result = $this->actionExecutor->execute($node, $context);
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => $normalized,
                    'result' => $result['success'] ? 'success' : 'failed',
                    'data' => $result,
                ];

                if ($result['success']) {
                    $actionCount++;
                    if (isset($result['message_id'])) {
                        $messageSent = true;
                    }

                    continue;
                }
            } else {
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => $normalized,
                    'result' => 'skipped',
                    'reason' => 'Non-executable node type',
                    'raw_type' => $node->type,
                ];
            }
        }

        return $logs;
    }

    protected function getNodeActionType(BotNode $node): ?string
    {
        $config = is_array($node->config) ? $node->config : [];

        return $config['action_type'] ?? (in_array($node->type, self::ACTION_TYPES, true) ? $node->type : null);
    }

    protected function shouldWaitForReply(BotNode $node): bool
    {
        return in_array($this->getNodeActionType($node), ['send_buttons', 'send_list'], true);
    }

    protected function isMessageAction(BotNode $node): bool
    {
        return in_array($this->getNodeActionType($node), [
            'send_text',
            'send_template',
            'send_buttons',
            'send_list',
            'send_media',
            'send_flow',
            'send_payment_link',
            'ai_agent_reply',
        ], true);
    }

    protected function isTerminalAction(BotNode $node): bool
    {
        return $this->getNodeActionType($node) === 'handoff';
    }

    protected function activeAutomationSession(WhatsAppConversation $conversation): ?array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $session = $metadata['automation_session'] ?? null;

        if (! is_array($session) || empty($session['flow_id']) || empty($session['waiting_node_id'])) {
            return null;
        }

        $bot = Bot::where('id', (int) ($session['bot_id'] ?? 0))
            ->where('account_id', $conversation->account_id)
            ->first();
        $timeoutMinutes = max(5, (int) ($bot?->session_timeout_minutes ?? 1440));
        $updatedAt = isset($session['updated_at']) ? \Illuminate\Support\Carbon::parse($session['updated_at']) : null;
        if ($updatedAt && $updatedAt->lt(now()->subMinutes($timeoutMinutes))) {
            $this->clearAutomationSession($conversation);

            if (($bot?->session_resume_mode ?? 'resume') === 'expire') {
                return [
                    'expired' => true,
                    'bot_id' => (int) ($session['bot_id'] ?? 0),
                    'flow_id' => (int) ($session['flow_id'] ?? 0),
                    'waiting_node_id' => (int) ($session['waiting_node_id'] ?? 0),
                    'expired_at' => now()->toIso8601String(),
                    'timeout_minutes' => $timeoutMinutes,
                ];
            }

            return null;
        }

        return $session;
    }

    public function startFlowForConversation(
        BotFlow $flow,
        WhatsAppMessage $inboundMessage,
        WhatsAppConversation $conversation,
        ?string $triggerEventId = null
    ): bool {
        $flow->loadMissing('bot');
        $account = $conversation->account;
        $connection = $conversation->connection;

        if (! $account || ! $connection || ! $flow->enabled || ! $flow->bot || $flow->bot->status !== 'active') {
            return false;
        }

        if ((int) $flow->account_id !== (int) $account->id || ! $flow->bot->appliesToConnection($connection->id)) {
            return false;
        }

        $triggerEventId ??= 'manual_flow:'.$flow->id.':'.($inboundMessage->meta_message_id ?? "msg_{$inboundMessage->id}");
        $existingExecution = BotExecution::where('account_id', $account->id)
            ->where('trigger_event_id', $triggerEventId)
            ->where('bot_flow_id', $flow->id)
            ->first();

        if ($existingExecution) {
            return true;
        }

        $context = new BotContext(
            account: $account,
            conversation: $conversation,
            inboundMessage: $inboundMessage,
            connection: $connection,
            metadata: ['started_by' => 'form_submission']
        );

        $execution = BotExecution::create([
            'account_id' => $account->id,
            'bot_id' => $flow->bot_id,
            'bot_flow_id' => $flow->id,
            'whatsapp_conversation_id' => $conversation->id,
            'trigger_event_id' => $triggerEventId,
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        $context->metadata['execution_id'] = $execution->id;

        try {
            $result = $this->executeGraph($flow, $context, $execution);
            $logs = $result['logs'];
            $actionCount = (int) $result['action_count'];

            if ($actionCount === 0) {
                $this->clearAutomationSession($conversation);
                $execution->update([
                    'status' => 'skipped',
                    'finished_at' => now(),
                    'error_message' => 'No executable actions were run for this form-triggered flow',
                    'logs' => array_slice($logs, 0, 100),
                ]);

                return true;
            }

            if (! empty($result['waiting_node_id'])) {
                $this->storeAutomationSession($conversation, $flow, (int) $result['waiting_node_id'], $execution);
            } else {
                $this->clearAutomationSession($conversation);
            }

            $execution->update([
                'status' => 'success',
                'finished_at' => now(),
                'logs' => array_slice($logs, 0, 100),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('chatbots')->error('Form-triggered bot execution failed', [
                'execution_id' => $execution->id,
                'flow_id' => $flow->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $this->clearAutomationSession($conversation);
            $execution->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return true;
        }
    }

    protected function storeAutomationSession(WhatsAppConversation $conversation, BotFlow $flow, int $waitingNodeId, BotExecution $execution, array $extra = []): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['automation_session'] = [
            'bot_id' => (int) $flow->bot_id,
            'flow_id' => (int) $flow->id,
            'waiting_node_id' => $waitingNodeId,
            'started_at' => $metadata['automation_session']['started_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'last_execution_id' => (int) $execution->id,
        ] + $extra;

        $conversation->forceFill(['metadata' => $metadata])->save();
        $conversation->refresh();
    }

    protected function clearAutomationSession(WhatsAppConversation $conversation): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        unset($metadata['automation_session']);

        $conversation->forceFill(['metadata' => $metadata])->save();
        $conversation->refresh();
    }

    protected function replyCandidates(BotContext $context): array
    {
        $payload = $context->inboundMessage->payload ?? [];
        $interactive = is_array($payload) ? ($payload['interactive'] ?? []) : [];
        $candidates = [
            $interactive['button_reply']['id'] ?? null,
            $interactive['button_reply']['title'] ?? null,
            $interactive['list_reply']['id'] ?? null,
            $interactive['list_reply']['title'] ?? null,
            $context->getMessageText(),
        ];

        return collect($candidates)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => mb_strtolower(trim($value)))
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveContinuationStartNodeId(BotFlow $flow, array $session, BotContext $context): ?int
    {
        $waitingNode = $flow->nodes()->find((int) $session['waiting_node_id']);
        if (! $waitingNode) {
            return null;
        }

        $edges = $flow->edges()
            ->where('from_node_id', $waitingNode->id)
            ->orderBy('sort_order')
            ->get()
            ->values();

        if ($edges->isEmpty()) {
            return null;
        }

        $candidates = $this->replyCandidates($context);
        foreach ($edges as $edge) {
            $label = is_string($edge->label) ? mb_strtolower(trim($edge->label)) : '';
            if ($label !== '' && in_array($label, $candidates, true)) {
                return (int) $edge->to_node_id;
            }
        }

        $config = is_array($waitingNode->config) ? $waitingNode->config : [];
        $buttons = $config['buttons'] ?? [];
        if (is_array($buttons)) {
            foreach (array_values($buttons) as $index => $button) {
                if (! is_array($button)) {
                    continue;
                }

                $buttonCandidates = collect([$button['id'] ?? null, $button['text'] ?? null])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->map(fn ($value) => mb_strtolower(trim($value)))
                    ->all();

                if (array_intersect($buttonCandidates, $candidates) !== []) {
                    $edge = $edges->get($index);

                    return $edge ? (int) $edge->to_node_id : null;
                }
            }
        }

        if ($edges->every(fn ($edge) => trim((string) $edge->label) === '')) {
            return (int) $edges->first()->to_node_id;
        }

        if ($candidates !== []) {
            return null;
        }

        return (int) $edges->first()->to_node_id;
    }

    protected function sendInvalidReplyFallback(BotFlow $flow, array $session, BotContext $context): bool
    {
        $invalidReplies = (int) ($session['invalid_replies'] ?? 0) + 1;
        $message = $invalidReplies >= 2
            ? 'I could not match that reply. I have paused this automation so a team member can help.'
            : 'Please choose one of the options above so I can continue.';

        $triggerEventId = 'session_invalid:'.$flow->id.':'.($context->inboundMessage->meta_message_id ?? "msg_{$context->inboundMessage->id}");
        $existingExecution = BotExecution::where('account_id', $context->account->id)
            ->where('trigger_event_id', $triggerEventId)
            ->where('bot_flow_id', $flow->id)
            ->first();

        if ($existingExecution) {
            return true;
        }

        $execution = BotExecution::create([
            'account_id' => $context->account->id,
            'bot_id' => $flow->bot_id,
            'bot_flow_id' => $flow->id,
            'whatsapp_conversation_id' => $context->conversation->id,
            'trigger_event_id' => $triggerEventId,
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        $context->metadata['execution_id'] = $execution->id;
        $fallbackNode = new BotNode([
            'account_id' => $context->account->id,
            'bot_flow_id' => $flow->id,
            'type' => 'action',
            'config' => [
                'action_type' => 'send_text',
                'message' => $message,
            ],
        ]);

        try {
            $result = $this->actionExecutor->execute($fallbackNode, $context);
            $logs = [[
                'node_id' => null,
                'type' => 'action',
                'result' => $result['success'] ? 'success' : 'failed',
                'reason' => 'Invalid automation session reply',
                'data' => $result,
            ]];

            if ($invalidReplies >= 2) {
                $this->clearAutomationSession($context->conversation);
            } else {
                $this->storeAutomationSession(
                    $context->conversation,
                    $flow,
                    (int) $session['waiting_node_id'],
                    $execution,
                    ['invalid_replies' => $invalidReplies]
                );
            }

            $execution->update([
                'status' => $result['success'] ? 'skipped' : 'failed',
                'finished_at' => now(),
                'error_message' => $result['success'] ? 'Invalid reply fallback sent' : ($result['error'] ?? 'Invalid reply fallback failed'),
                'logs' => $logs,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->clearAutomationSession($context->conversation);
            $execution->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return true;
        }
    }

    protected function executeGraph(BotFlow $flow, BotContext $context, BotExecution $execution, ?int $startNodeId = null): array
    {
        $logs = [];
        $actionCount = 0;
        $maxActions = 10;
        $waitingNodeId = null;
        $terminal = false;

        $nodes = $flow->nodes()->get()->keyBy('id');
        $edgesByFrom = $flow->edges()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('from_node_id');

        $nodeTypeSummary = $nodes
            ->values()
            ->map(fn (BotNode $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'normalized' => $this->normalizeNodeType($n),
                'has_config' => is_array($n->config) && ! empty($n->config),
                'config_keys' => is_array($n->config) ? array_slice(array_keys($n->config), 0, 12) : [],
            ])
            ->take(25)
            ->all();

        Log::channel('chatbots')->debug('Flow graph snapshot', [
            'execution_id' => $execution->id,
            'bot_id' => $flow->bot_id,
            'flow_id' => $flow->id,
            'nodes_count' => $nodes->count(),
            'edges_count' => $edgesByFrom->flatten(1)->count(),
            'has_edges' => ! $edgesByFrom->isEmpty(),
            'start_node_id' => $startNodeId,
            'nodes' => $nodeTypeSummary,
        ]);

        if ($nodes->isEmpty()) {
            return [
                'logs' => [['result' => 'skipped', 'reason' => 'No nodes found']],
                'action_count' => 0,
                'waiting_node_id' => null,
                'terminal' => true,
                'nodes_count' => 0,
                'has_edges' => false,
            ];
        }

        if ($edgesByFrom->isEmpty()) {
            Log::channel('chatbots')->debug('Executing flow in linear mode (no edges)', [
                'execution_id' => $execution->id,
                'flow_id' => $flow->id,
            ]);

            $logs = $this->executeLinear($nodes, $context, $maxActions, $actionCount);

            return [
                'logs' => $logs,
                'action_count' => $actionCount,
                'waiting_node_id' => null,
                'terminal' => true,
                'nodes_count' => $nodes->count(),
                'has_edges' => false,
            ];
        }

        $startNode = $startNodeId ? $nodes->get($startNodeId) : $this->resolveStartNode($nodes, $edgesByFrom);
        if (! $startNode) {
            return [
                'logs' => [['result' => 'skipped', 'reason' => 'No start node found']],
                'action_count' => 0,
                'waiting_node_id' => null,
                'terminal' => true,
                'nodes_count' => $nodes->count(),
                'has_edges' => true,
            ];
        }

        Log::channel('chatbots')->debug('Executing flow in graph mode', [
            'execution_id' => $execution->id,
            'flow_id' => $flow->id,
            'start_node_id' => $startNode->id,
        ]);

        $queue = [$startNode->id];
        $visited = [];
        $messageSent = false;

        while (! empty($queue)) {
            $nodeId = array_shift($queue);
            if (! $nodeId || isset($visited[$nodeId])) {
                continue;
            }

            $visited[$nodeId] = true;
            $node = $nodes->get($nodeId);
            if (! $node) {
                continue;
            }

            $normalized = $this->normalizeNodeType($node);

            if ($actionCount >= $maxActions) {
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => $normalized,
                    'result' => 'skipped',
                    'reason' => 'Max actions limit reached',
                ];
                break;
            }

            if ($normalized === 'condition') {
                $passed = $this->conditionEvaluator->evaluate($node, $context);
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => 'condition',
                    'result' => $passed ? 'passed' : 'failed',
                ];

                $edges = $edgesByFrom->get($node->id, collect());
                $labelToFollow = $passed ? 'true' : 'false';
                $next = $edges->first(fn ($edge) => $edge->label === $labelToFollow)
                    ?? $edges->first();
                if ($next) {
                    $queue[] = $next->to_node_id;
                }

                continue;
            }

            if ($normalized === 'action' || $normalized === 'delay' || $normalized === 'webhook') {
                if ($node->type !== $normalized && $normalized !== 'passthrough') {
                    $node->type = $normalized;
                }

                if ($normalized === 'action' && $messageSent && $this->isMessageAction($node)) {
                    $logs[] = [
                        'node_id' => $node->id,
                        'type' => $normalized,
                        'result' => 'skipped',
                        'reason' => 'Only one customer-facing message is allowed per automation execution',
                    ];

                    $edges = $edgesByFrom->get($node->id, collect());
                    foreach ($edges as $edge) {
                        if ($edge->to_node_id && ! isset($visited[$edge->to_node_id])) {
                            $queue[] = $edge->to_node_id;
                        }
                    }

                    continue;
                }

                $result = $this->actionExecutor->execute($node, $context);
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => $normalized,
                    'result' => $result['success'] ? 'success' : 'failed',
                    'data' => $result,
                ];

                if ($result['success']) {
                    $actionCount++;
                    if (isset($result['message_id'])) {
                        $messageSent = true;
                    }
                    Log::channel('chatbots')->info('Action executed', [
                        'execution_id' => $execution->id,
                        'flow_id' => $flow->id,
                        'node_id' => $node->id,
                        'node_type' => $normalized,
                        'action_type' => $normalized === 'action' ? $this->getNodeActionType($node) : null,
                        'success' => true,
                    ]);

                    $edges = $edgesByFrom->get($node->id, collect());
                    if (($this->shouldWaitForReply($node) || ($messageSent && $this->isMessageAction($node))) && $edges->isNotEmpty()) {
                        $waitingNodeId = (int) $node->id;
                        break;
                    }

                    if ($this->isTerminalAction($node)) {
                        $terminal = true;
                        break;
                    }
                } else {
                    Log::channel('chatbots')->warning('Action failed', [
                        'node_id' => $node->id,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                }
            } else {
                $logs[] = [
                    'node_id' => $node->id,
                    'type' => $normalized,
                    'result' => 'skipped',
                    'reason' => 'Non-executable node type',
                    'raw_type' => $node->type,
                ];
            }

            $edges = $edgesByFrom->get($node->id, collect());
            foreach ($edges as $edge) {
                if ($edge->to_node_id && ! isset($visited[$edge->to_node_id])) {
                    $queue[] = $edge->to_node_id;
                }
            }
        }

        return [
            'logs' => $logs,
            'action_count' => $actionCount,
            'waiting_node_id' => $waitingNodeId,
            'terminal' => $terminal || $waitingNodeId === null,
            'nodes_count' => $nodes->count(),
            'has_edges' => ! $edgesByFrom->isEmpty(),
        ];
    }

    /**
     * Process inbound message for bots.
     */
    public function processInboundMessage(
        WhatsAppMessage $inboundMessage,
        WhatsAppConversation $conversation
    ): bool {
        $account = $conversation->account;
        $connection = $conversation->connection;
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        if (($metadata['bot_paused'] ?? false) === true) {
            Log::channel('chatbots')->info('Inbound message skipped: bot paused for conversation', [
                'account_id' => $conversation->account_id,
                'conversation_id' => $conversation->id,
                'message_id' => $inboundMessage->id,
                'paused_reason' => $metadata['bot_paused_reason'] ?? null,
            ]);

            return false;
        }

        if (! $account || ! $connection) {
            Log::channel('chatbots')->warning('Inbound message skipped: missing account/connection context', [
                'conversation_id' => $conversation->id,
                'message_id' => $inboundMessage->id,
                'account_present' => (bool) $account,
                'connection_present' => (bool) $connection,
            ]);

            return false;
        }

        // Get eligible bots
        $bots = Bot::where('account_id', $account->id)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->filter(fn (Bot $bot) => $bot->appliesToConnection($connection->id));

        if ($bots->isEmpty()) {
            Log::channel('chatbots')->debug('No eligible bots for inbound message', [
                'account_id' => $account->id,
                'conversation_id' => $conversation->id,
                'connection_id' => $connection->id,
                'message_id' => $inboundMessage->id,
            ]);

            return false;
        }

        Log::channel('chatbots')->debug('Eligible bots for inbound message', [
            'account_id' => $account->id,
            'conversation_id' => $conversation->id,
            'connection_id' => $connection->id,
            'message_id' => $inboundMessage->id,
            'bots' => $bots->map(fn (Bot $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'applies_to' => $b->applies_to,
            ])->values()->all(),
        ]);

        // Create context
        $context = new BotContext(
            account: $account,
            conversation: $conversation,
            inboundMessage: $inboundMessage,
            connection: $connection
        );

        if ($this->processActiveSession($context, $bots)) {
            return true;
        }

        $matched = false;

        // Allow only one bot to answer a single inbound turn. Multiple active
        // bots on the same WABA otherwise create duplicate replies and
        // conflicting sessions.
        foreach ($bots as $bot) {
            $flows = $bot->flows()
                ->where('enabled', true)
                ->orderBy('priority')
                ->get();

            if ($flows->isEmpty()) {
                Log::channel('chatbots')->warning('Active bot has no enabled flows', [
                    'account_id' => $account->id,
                    'bot_id' => $bot->id,
                    'bot_name' => $bot->name,
                    'conversation_id' => $conversation->id,
                    'message_id' => $inboundMessage->id,
                ]);

                continue;
            }

            foreach ($flows as $flow) {
                $ran = $this->processFlow($flow, $context);
                $matched = $matched || $ran;
                if ($ran) {
                    Log::channel('chatbots')->info('Inbound message handled by bot', [
                        'account_id' => $account->id,
                        'conversation_id' => $conversation->id,
                        'connection_id' => $connection->id,
                        'message_id' => $inboundMessage->id,
                        'bot_id' => $bot->id,
                        'flow_id' => $flow->id,
                    ]);

                    return true;
                }
            }
        }

        return $matched;
    }

    protected function processActiveSession(BotContext $context, $eligibleBots): bool
    {
        $session = $this->activeAutomationSession($context->conversation);
        if (! $session) {
            return false;
        }

        if (($session['expired'] ?? false) === true) {
            Log::channel('chatbots')->info('Automation session expired', [
                'account_id' => $context->account->id,
                'conversation_id' => $context->conversation->id,
                'flow_id' => $session['flow_id'] ?? null,
                'timeout_minutes' => $session['timeout_minutes'] ?? null,
            ]);

            return true;
        }

        $eligibleBotIds = $eligibleBots->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array((int) ($session['bot_id'] ?? 0), $eligibleBotIds, true)) {
            $this->clearAutomationSession($context->conversation);

            return false;
        }

        $flow = BotFlow::where('account_id', $context->account->id)
            ->where('id', (int) $session['flow_id'])
            ->where('bot_id', (int) $session['bot_id'])
            ->where('enabled', true)
            ->first();

        if (! $flow) {
            $this->clearAutomationSession($context->conversation);

            return false;
        }

        $startNodeId = $this->resolveContinuationStartNodeId($flow, $session, $context);
        if (! $startNodeId) {
            return $this->sendInvalidReplyFallback($flow, $session, $context);
        }

        $triggerEventId = 'session:'.$flow->id.':'.($context->inboundMessage->meta_message_id ?? "msg_{$context->inboundMessage->id}");
        $existingExecution = BotExecution::where('account_id', $context->account->id)
            ->where('trigger_event_id', $triggerEventId)
            ->where('bot_flow_id', $flow->id)
            ->first();

        if ($existingExecution) {
            Log::channel('chatbots')->debug('Skipping duplicate session continuation', [
                'execution_id' => $existingExecution->id,
                'trigger_event_id' => $triggerEventId,
            ]);

            return true;
        }

        Log::channel('chatbots')->info('Continuing active automation session', [
            'account_id' => $context->account->id,
            'bot_id' => $flow->bot_id,
            'flow_id' => $flow->id,
            'conversation_id' => $context->conversation->id,
            'waiting_node_id' => $session['waiting_node_id'],
            'start_node_id' => $startNodeId,
            'message_id' => $context->inboundMessage->id,
            'meta_message_id' => $context->inboundMessage->meta_message_id,
        ]);

        $execution = BotExecution::create([
            'account_id' => $context->account->id,
            'bot_id' => $flow->bot_id,
            'bot_flow_id' => $flow->id,
            'whatsapp_conversation_id' => $context->conversation->id,
            'trigger_event_id' => $triggerEventId,
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        $context->metadata['execution_id'] = $execution->id;
        $context->metadata['automation_session'] = $session;

        try {
            $result = $this->executeGraph($flow, $context, $execution, $startNodeId);
            $logs = $result['logs'];
            $actionCount = (int) $result['action_count'];

            if ($actionCount === 0) {
                $this->clearAutomationSession($context->conversation);
                $execution->update([
                    'status' => 'skipped',
                    'finished_at' => now(),
                    'error_message' => 'Automation session continued but no actions were run',
                    'logs' => array_slice($logs, 0, 100),
                ]);

                return true;
            }

            if (! empty($result['waiting_node_id'])) {
                $this->storeAutomationSession($context->conversation, $flow, (int) $result['waiting_node_id'], $execution);
            } else {
                $this->clearAutomationSession($context->conversation);
            }

            $execution->update([
                'status' => 'success',
                'finished_at' => now(),
                'logs' => array_slice($logs, 0, 100),
            ]);
        } catch (\Throwable $e) {
            Log::channel('chatbots')->error('Automation session continuation failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->clearAutomationSession($context->conversation);
            $execution->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Process a single flow. Returns true if the trigger matched and execution was started.
     */
    protected function processFlow(BotFlow $flow, BotContext $context): bool
    {
        // Check idempotency
        $triggerEventId = $context->inboundMessage->meta_message_id ??
            "msg_{$context->inboundMessage->id}";

        $existingExecution = BotExecution::where('account_id', $context->account->id)
            ->where('trigger_event_id', $triggerEventId)
            ->where('bot_flow_id', $flow->id)
            ->first();

        if ($existingExecution) {
            Log::channel('chatbots')->debug('Skipping duplicate execution', [
                'execution_id' => $existingExecution->id,
                'trigger_event_id' => $triggerEventId]);

            return false;
        }

        // Check trigger
        if (! $this->triggerEvaluator->matches($flow, $context)) {
            Log::channel('chatbots')->debug('Flow trigger did not match', [
                'account_id' => $context->account->id,
                'flow_id' => $flow->id,
                'flow_name' => $flow->name,
                'trigger' => $flow->trigger,
                'message_id' => $context->inboundMessage->id,
                'meta_message_id' => $context->inboundMessage->meta_message_id,
                'message_type' => $context->inboundMessage->type,
                'message_text' => $context->getMessageText(),
                'connection_id' => $context->getConnectionId(),
                'conversation_id' => $context->conversation->id,
            ]);

            return false;
        }

        $cooldownMinutes = (int) ($flow->trigger['cooldown_minutes'] ?? 0);
        if ($cooldownMinutes > 0) {
            $recentExecution = BotExecution::where('account_id', $context->account->id)
                ->where('bot_flow_id', $flow->id)
                ->where('whatsapp_conversation_id', $context->conversation->id)
                ->where('created_at', '>=', now()->subMinutes($cooldownMinutes))
                ->latest()
                ->first();

            if ($recentExecution) {
                Log::channel('chatbots')->info('Flow trigger matched but cooldown is active', [
                    'account_id' => $context->account->id,
                    'bot_id' => $flow->bot_id,
                    'flow_id' => $flow->id,
                    'flow_name' => $flow->name,
                    'conversation_id' => $context->conversation->id,
                    'message_id' => $context->inboundMessage->id,
                    'cooldown_minutes' => $cooldownMinutes,
                    'recent_execution_id' => $recentExecution->id,
                ]);

                return false;
            }
        }

        Log::channel('chatbots')->info('Flow trigger matched, starting execution', [
            'account_id' => $context->account->id,
            'bot_id' => $flow->bot_id,
            'flow_id' => $flow->id,
            'flow_name' => $flow->name,
            'trigger' => $flow->trigger,
            'conversation_id' => $context->conversation->id,
            'message_id' => $context->inboundMessage->id,
            'meta_message_id' => $context->inboundMessage->meta_message_id,
        ]);

        // Create execution record
        $execution = BotExecution::create([
            'account_id' => $context->account->id,
            'bot_id' => $flow->bot_id,
            'bot_flow_id' => $flow->id,
            'whatsapp_conversation_id' => $context->conversation->id,
            'trigger_event_id' => $triggerEventId,
            'status' => 'running',
            'started_at' => now(),
            'logs' => []]);

        // Update context with execution ID
        $context->metadata['execution_id'] = $execution->id;

        try {
            $result = $this->executeGraph($flow, $context, $execution);
            $logs = $result['logs'];
            $actionCount = (int) $result['action_count'];

            if ($actionCount === 0) {
                Log::channel('chatbots')->warning('Execution completed but no actions were executed', [
                    'execution_id' => $execution->id,
                    'flow_id' => $flow->id,
                    'nodes_count' => $result['nodes_count'],
                    'has_edges' => $result['has_edges'],
                ]);

                $this->clearAutomationSession($context->conversation);
                $execution->update([
                    'status' => 'skipped',
                    'finished_at' => now(),
                    'error_message' => 'No executable actions were run for this flow',
                    'logs' => array_slice($logs, 0, 100),
                ]);

                return true;
            }

            if (! empty($result['waiting_node_id'])) {
                $this->storeAutomationSession($context->conversation, $flow, (int) $result['waiting_node_id'], $execution);
            } else {
                $this->clearAutomationSession($context->conversation);
            }

            // Update execution
            $execution->update([
                'status' => 'success',
                'finished_at' => now(),
                'logs' => array_slice($logs, 0, 100), // Cap logs size
            ]);
        } catch (\Exception $e) {
            Log::channel('chatbots')->error('Bot execution failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()]);

            $execution->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage()]);

            app(\App\Services\AppNotificationService::class)->workspace(
                $execution->account_id,
                'automation_failed',
                'Automation failed',
                $e->getMessage(),
                'warning',
                route('app.chatbots.index', ['bot' => $execution->bot_id]),
                ['execution_id' => $execution->id, 'bot_id' => $execution->bot_id, 'flow_id' => $execution->bot_flow_id, 'dedupe_key' => 'bot_'.$execution->bot_id]
            );
            app(\App\Services\AppNotificationService::class)->platform(
                'failed_automation',
                'Workspace automation failed',
                $e->getMessage(),
                'warning',
                route('platform.activity-logs', ['type' => 'billing_automation_failed']),
                ['execution_id' => $execution->id, 'account_id' => $execution->account_id, 'bot_id' => $execution->bot_id, 'dedupe_key' => 'account_'.$execution->account_id.'_bot_'.$execution->bot_id]
            );
        }

        return true;
    }
}
