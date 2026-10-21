<?php

namespace App\Modules\Chatbots\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBotRequest;
use App\Http\Requests\UpdateBotRequest;
use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotActionJob;
use App\Modules\Chatbots\Models\BotEdge;
use App\Modules\Chatbots\Models\BotExecution;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\Contacts\Models\ContactSegment;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppFlow;
use App\Modules\WhatsApp\Models\WhatsAppList;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BotController extends Controller
{
    protected function flowHealth(BotFlow $flow): array
    {
        $nodes = $flow->nodes;
        $hasNodes = $nodes->isNotEmpty();
        $hasExecutableNode = $nodes->contains(fn (BotNode $node) => in_array($node->type, ['action', 'delay', 'webhook'], true));
        $hasStartNode = $nodes->contains(fn (BotNode $node) => (bool) ($node->config['is_start'] ?? false));
        $hasEdges = $flow->edges->isNotEmpty();

        return [
            'has_nodes' => $hasNodes,
            'has_executable_node' => $hasExecutableNode,
            'has_start_node' => $hasStartNode,
            'has_edges' => $hasEdges,
            'is_runnable' => $flow->enabled && $hasNodes && $hasExecutableNode,
        ];
    }

    /**
     * Display a listing of bots.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('viewAny', [Bot::class, $account]);

        $sevenDaysAgo = now()->subDays(7);
        $bots = Bot::where('account_id', $account->id)
            ->with(['creator', 'updater', 'flows' => ['nodes', 'edges']])
            ->withCount([
                'executions as executions_last_7_count' => fn ($q) => $q->where('created_at', '>=', $sevenDaysAgo),
                'executions as errors_last_7_count' => fn ($q) => $q->where('created_at', '>=', $sevenDaysAgo)->where('status', 'failed'),
            ])
            ->withAggregate(['executions as last_run_at' => fn ($q) => $q->where('created_at', '>=', $sevenDaysAgo)], 'created_at', 'max')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($bot) {
                $enabledFlows = $bot->flows->where('enabled', true);
                $runnableFlows = $enabledFlows->filter(function (BotFlow $flow) {
                    $health = $this->flowHealth($flow);

                    return $health['is_runnable'] === true;
                });

                $lastRunAt = isset($bot->last_run_at) && $bot->last_run_at !== null
                    ? \Illuminate\Support\Carbon::parse($bot->last_run_at)
                    : null;

                return [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'description' => $bot->description,
                    'status' => $bot->status,
                    'is_default' => $bot->is_default,
                    'applies_to' => $bot->applies_to,
                    'version' => $bot->version,
                    'flows_count' => $bot->flows->count(),
                    'enabled_flows_count' => $enabledFlows->count(),
                    'runnable_flows_count' => $runnableFlows->count(),
                    'is_runnable' => $bot->status === 'active' ? $runnableFlows->isNotEmpty() : true,
                    'executions_count' => (int) ($bot->executions_last_7_count ?? 0),
                    'errors_count' => (int) ($bot->errors_last_7_count ?? 0),
                    'last_run_at' => $lastRunAt?->toIso8601String(),
                    'created_at' => $bot->created_at->toIso8601String()];
            });

        return Inertia::render('Chatbots/Index', [
            'account' => $account,
            'bots' => $bots,
            'connections' => WhatsAppConnection::where('account_id', $account->id)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'automationOptions' => [
                'agents' => $account->getAssignableAgents(),
                'tags' => ContactTag::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'color']),
                'segments' => ContactSegment::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'contact_count']),
                'templates' => WhatsAppTemplate::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'language', 'status']),
                'lists' => WhatsAppList::where('account_id', $account->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'flows' => WhatsAppFlow::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'meta_flow_id', 'status']),
            ],
            'selectedBot' => $this->selectedBotPayload($request, $account),
        ]);
    }

    protected function selectedBotPayload(Request $request, $account): ?array
    {
        $selected = $request->query('bot');
        if (! $selected) {
            return null;
        }

        $bot = Bot::where('account_id', $account->id)
            ->where('id', $selected)
            ->with(['creator', 'updater', 'flows' => ['nodes', 'edges']])
            ->withCount([
                'executions',
                'executions as failed_executions_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->first();

        if (! $bot) {
            return null;
        }

        return $this->botPayload($account, $bot);
    }

    public function builder(Request $request, Bot $bot): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('viewAny', [Bot::class, $account]);

        if (! account_ids_match($bot->account_id, $account->id)) {
            abort(404);
        }

        $bot->load(['creator', 'updater', 'flows' => ['nodes', 'edges']]);
        $bot->loadCount([
            'executions',
            'executions as failed_executions_count' => fn ($query) => $query->where('status', 'failed'),
        ]);

        return Inertia::render('Chatbots/Builder', [
            'account' => $account,
            'bot' => $this->botPayload($account, $bot),
            'connections' => WhatsAppConnection::where('account_id', $account->id)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'automationOptions' => [
                'agents' => $account->getAssignableAgents(),
                'tags' => ContactTag::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'color']),
                'segments' => ContactSegment::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'contact_count']),
                'templates' => WhatsAppTemplate::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'language', 'status']),
                'lists' => WhatsAppList::where('account_id', $account->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'flows' => WhatsAppFlow::where('account_id', $account->id)->orderBy('name')->get(['id', 'name', 'meta_flow_id', 'status']),
            ],
        ]);
    }

    protected function botPayload($account, Bot $bot): array
    {
        $executions = BotExecution::where('account_id', $account->id)
            ->where('bot_id', $bot->id)
            ->with('flow:id,name')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (BotExecution $execution) => [
                'id' => $execution->id,
                'status' => $execution->status,
                'flow_name' => $execution->flow?->name,
                'started_at' => $execution->started_at?->toIso8601String(),
                'finished_at' => $execution->finished_at?->toIso8601String(),
                'duration_ms' => $execution->finished_at && $execution->started_at ? $execution->started_at->diffInMilliseconds($execution->finished_at) : null,
                'error_message' => $execution->error_message,
                'logs' => $execution->logs ?: [],
                'conversation_id' => $execution->whatsapp_conversation_id,
            ]);

        $analyticsExecutions = BotExecution::where('account_id', $account->id)
            ->where('bot_id', $bot->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['id', 'status', 'logs', 'created_at']);
        $nodeStats = [];
        $dropOffs = [];
        foreach ($analyticsExecutions as $execution) {
            $logs = is_array($execution->logs) ? $execution->logs : [];
            foreach ($logs as $log) {
                if (! is_array($log) || empty($log['node_id'])) {
                    continue;
                }

                $nodeId = (int) $log['node_id'];
                $nodeStats[$nodeId] ??= [
                    'node_id' => $nodeId,
                    'type' => $log['type'] ?? 'node',
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                ];
                $nodeStats[$nodeId]['total']++;
                $result = $log['result'] ?? 'unknown';
                if (isset($nodeStats[$nodeId][$result])) {
                    $nodeStats[$nodeId][$result]++;
                }
                if (in_array($result, ['failed', 'skipped'], true)) {
                    $dropOffs[$nodeId] = ($dropOffs[$nodeId] ?? 0) + 1;
                }
            }
        }

        return [
            'id' => $bot->id,
            'name' => $bot->name,
            'description' => $bot->description,
            'status' => $bot->status,
            'is_default' => $bot->is_default,
            'applies_to' => $bot->applies_to ?: ['all_connections' => true, 'connection_ids' => []],
            'stop_on_first_flow' => (bool) $bot->stop_on_first_flow,
            'session_timeout_minutes' => (int) ($bot->session_timeout_minutes ?? 1440),
            'session_resume_mode' => $bot->session_resume_mode ?: 'resume',
            'session_expired_message' => $bot->session_expired_message,
            'version' => $bot->version,
            'executions_count' => (int) ($bot->executions_count ?? 0),
            'failed_executions_count' => (int) ($bot->failed_executions_count ?? 0),
            'created_at' => $bot->created_at?->toIso8601String(),
            'updated_at' => $bot->updated_at?->toIso8601String(),
            'created_by' => $bot->creator ? ['id' => $bot->creator->id, 'name' => $bot->creator->name] : null,
            'updated_by' => $bot->updater ? ['id' => $bot->updater->id, 'name' => $bot->updater->name] : null,
            'flows' => $bot->flows->map(function (BotFlow $flow) {
                $health = $this->flowHealth($flow);

                return [
                    'id' => $flow->id,
                    'name' => $flow->name,
                    'enabled' => $flow->enabled,
                    'priority' => $flow->priority,
                    'trigger' => $flow->trigger ?: [],
                    'health' => $health,
                    'nodes' => $flow->nodes->map(fn (BotNode $node) => [
                        'id' => $node->id,
                        'type' => $node->type,
                        'config' => $node->config ?: [],
                        'sort_order' => $node->sort_order,
                        'pos_x' => $node->pos_x,
                        'pos_y' => $node->pos_y,
                    ]),
                    'edges' => $flow->edges->map(fn (BotEdge $edge) => [
                        'id' => $edge->id,
                        'from_node_id' => $edge->from_node_id,
                        'to_node_id' => $edge->to_node_id,
                        'label' => $edge->label,
                        'sort_order' => $edge->sort_order,
                    ]),
                ];
            }),
            'executions' => $executions,
            'analytics' => [
                'window_days' => 30,
                'runs' => $analyticsExecutions->count(),
                'success' => $analyticsExecutions->where('status', 'success')->count(),
                'failed' => $analyticsExecutions->where('status', 'failed')->count(),
                'skipped' => $analyticsExecutions->where('status', 'skipped')->count(),
                'node_hits' => collect($nodeStats)->sortByDesc('total')->values()->take(12)->all(),
                'drop_offs' => collect($dropOffs)
                    ->map(fn ($count, $nodeId) => ['node_id' => (int) $nodeId, 'count' => (int) $count])
                    ->sortByDesc('count')
                    ->values()
                    ->take(8)
                    ->all(),
            ],
        ];
    }

    /**
     * Show the form for creating a new bot.
     */
    public function create(Request $request): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        Gate::authorize('manage', [Bot::class, $account]);

        return redirect()->route('app.chatbots.index', ['panel' => 'create']);
    }

    /**
     * Store a newly created bot.
     */
    public function store(StoreBotRequest $request)
    {
        $account = $request->attributes->get('account') ?? current_account();
        Gate::authorize('manage', [Bot::class, $account]);

        $validated = $request->validated();
        $bot = DB::transaction(function () use ($account, $request, $validated) {
            $bot = Bot::create([
                'account_id' => $account->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'applies_to' => $validated['applies_to'],
                'stop_on_first_flow' => $validated['stop_on_first_flow'] ?? true,
                'session_timeout_minutes' => $validated['session_timeout_minutes'] ?? 1440,
                'session_resume_mode' => $validated['session_resume_mode'] ?? 'resume',
                'session_expired_message' => $validated['session_expired_message'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->createStarterFlow($account->id, $bot, $validated);

            return $bot;
        });

        return redirect()->route('app.chatbots.index', [
            'bot' => $bot->id])->with('success', 'Chatbot created.');
    }

    protected function createStarterFlow(int $accountId, Bot $bot, array $validated): void
    {
        $mode = $validated['starter_flow_mode'] ?? 'guided';
        if ($mode !== 'guided') {
            return;
        }

        $triggerType = $validated['starter_trigger_type'] ?? 'inbound_message';
        $replyMessage = trim((string) ($validated['starter_reply_message'] ?? 'Hi! Thanks for messaging us. A team member will get back to you shortly.'));

        if ($replyMessage === '') {
            $replyMessage = 'Hi! Thanks for messaging us. A team member will get back to you shortly.';
        }

        $trigger = ['type' => $triggerType];
        if ($triggerType === 'keyword') {
            $raw = (string) ($validated['starter_keywords'] ?? '');
            $keywords = array_values(array_filter(array_map(
                static fn ($keyword) => trim($keyword),
                preg_split('/[\n,]+/', $raw) ?: []
            )));
            $trigger['keywords'] = ! empty($keywords) ? $keywords : ['hi'];
            $trigger['match_type'] = 'any';
            $trigger['case_sensitive'] = false;
            $trigger['whole_word'] = false;
        } else {
            $trigger['first_message_only'] = false;
        }

        $flow = BotFlow::create([
            'account_id' => $accountId,
            'bot_id' => $bot->id,
            'name' => 'Quick Start Flow',
            'trigger' => $trigger,
            'enabled' => true,
            'priority' => 100,
        ]);

        BotNode::create([
            'account_id' => $accountId,
            'bot_flow_id' => $flow->id,
            'type' => 'action',
            'config' => [
                'is_start' => true,
                'action_type' => 'send_text',
                'message' => $replyMessage,
            ],
            'sort_order' => 1,
            'pos_x' => 360,
            'pos_y' => 120,
        ]);
    }

    /**
     * Display the specified bot.
     */
    public function show(Request $request, Bot $bot): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('viewAny', [Bot::class, $account]);

        if (! account_ids_match($bot->account_id, $account->id)) {
            abort(404);
        }

        return redirect()->route('app.chatbots.index', ['bot' => $bot->id]);
    }

    /**
     * Update the specified bot.
     */
    public function update(UpdateBotRequest $request, Bot $bot)
    {
        $account = $request->attributes->get('account') ?? current_account();
        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($bot->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validated();
        $wasDraft = $bot->status === 'draft';
        $isPublishing = $wasDraft && $validated['status'] === 'active';

        $bot->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'applies_to' => $validated['applies_to'],
            'stop_on_first_flow' => $validated['stop_on_first_flow'] ?? $bot->stop_on_first_flow ?? true,
            'session_timeout_minutes' => $validated['session_timeout_minutes'] ?? $bot->session_timeout_minutes ?? 1440,
            'session_resume_mode' => $validated['session_resume_mode'] ?? $bot->session_resume_mode ?? 'resume',
            'session_expired_message' => $validated['session_expired_message'] ?? null,
            'version' => $isPublishing ? $bot->version + 1 : $bot->version,
            'updated_by' => $request->user()->id]);

        $runnableFlowsCount = $bot->flows()
            ->where('enabled', true)
            ->whereHas('nodes', function ($query) {
                $query->whereIn('type', ['action', 'delay', 'webhook']);
            })
            ->count();

        if ($bot->status === 'active' && $runnableFlowsCount === 0) {
            return redirect()->back()
                ->with('success', 'Chatbot saved.')
                ->with('warning', 'This chatbot is on, but it has no steps yet. Add a flow so it can reply.');
        }

        return redirect()->back()->with('success', 'Chatbot saved.');
    }

    public function test(Request $request, Bot $bot)
    {
        $account = $request->attributes->get('account') ?? current_account();
        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($bot->account_id, $account->id)) {
            abort(404);
        }

        $bot->load(['flows' => ['nodes', 'edges']]);
        $flow = $bot->flows
            ->sortByDesc(fn (BotFlow $candidate) => (int) $candidate->enabled)
            ->sortBy('priority')
            ->first();

        if (! $flow) {
            return back()->with('error', 'Add a flow before testing this chatbot.');
        }

        $health = $this->flowHealth($flow);
        $logs = [
            ['level' => 'info', 'message' => 'Test mode started. No WhatsApp message, webhook, tag, assignment, or delay action was sent.', 'at' => now()->toIso8601String()],
            ['level' => 'info', 'message' => "Flow '{$flow->name}' selected for dry run.", 'at' => now()->toIso8601String()],
            ['level' => $flow->enabled ? 'info' : 'warning', 'message' => $flow->enabled ? 'Flow is enabled.' : 'Flow is disabled.', 'at' => now()->toIso8601String()],
            ['level' => $health['has_executable_node'] ? 'info' : 'warning', 'message' => $health['has_executable_node'] ? 'Executable node found.' : 'No executable action, delay, or webhook node found.', 'at' => now()->toIso8601String()],
            ['level' => 'info', 'message' => $flow->nodes->count().' node(s), '.$flow->edges->count().' edge(s) checked.', 'at' => now()->toIso8601String()],
        ];

        $status = $health['has_nodes'] && $health['has_executable_node'] ? 'success' : 'failed';

        BotExecution::create([
            'account_id' => $account->id,
            'bot_id' => $bot->id,
            'bot_flow_id' => $flow->id,
            'trigger_event_id' => 'test:'.Str::uuid(),
            'status' => $status,
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => $status === 'failed' ? 'Flow is not runnable yet.' : null,
            'logs' => $logs,
        ]);

        return redirect()
            ->route('app.chatbots.index', ['bot' => $bot->id])
            ->with($status === 'success' ? 'success' : 'warning', $status === 'success' ? 'Automation test completed.' : 'Automation test found missing flow setup.');
    }

    public function simulate(Request $request, Bot $bot)
    {
        $account = $request->attributes->get('account') ?? current_account();
        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($bot->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
            'button_id' => ['nullable', 'string', 'max:100'],
            'flow_id' => ['nullable', 'integer'],
        ]);

        $message = trim((string) ($validated['message'] ?? ''));
        $buttonId = trim((string) ($validated['button_id'] ?? ''));

        $flows = $bot->flows()
            ->with(['nodes', 'edges'])
            ->where('enabled', true)
            ->when($validated['flow_id'] ?? null, fn ($query, $flowId) => $query->whereKey($flowId))
            ->orderBy('priority')
            ->get();

        $trace = [];
        foreach ($flows as $flow) {
            if (! $this->simulateTriggerMatches($flow->trigger ?: [], $message, $buttonId)) {
                $trace[] = [
                    'flow_id' => $flow->id,
                    'flow_name' => $flow->name,
                    'matched' => false,
                    'message' => 'Trigger did not match.',
                    'steps' => [],
                ];

                continue;
            }

            $trace[] = [
                'flow_id' => $flow->id,
                'flow_name' => $flow->name,
                'matched' => true,
                'message' => 'Trigger matched. Dry-run only: no messages or side effects were sent.',
                'safety' => [
                    'cooldown_minutes' => (int) ($flow->trigger['cooldown_minutes'] ?? 0),
                    'stop_on_first_flow' => (bool) $bot->stop_on_first_flow,
                ],
                'steps' => $this->simulateFlowSteps($flow, $message),
            ];

            if ($bot->stop_on_first_flow) {
                break;
            }
        }

        return response()->json([
            'input' => ['message' => $message, 'button_id' => $buttonId],
            'matched' => collect($trace)->contains(fn ($item) => $item['matched'] === true),
            'trace' => $trace,
        ]);
    }

    protected function simulateTriggerMatches(array $trigger, string $message, string $buttonId = ''): bool
    {
        $type = $trigger['type'] ?? null;
        if ($type === 'inbound_message') {
            return true;
        }

        if ($type === 'button_reply') {
            return $buttonId !== '' && hash_equals((string) ($trigger['button_id'] ?? ''), $buttonId);
        }

        if ($type !== 'keyword') {
            return false;
        }

        $keywords = array_values(array_filter(array_map(
            static fn ($keyword) => is_string($keyword) ? trim($keyword) : '',
            $trigger['keywords'] ?? []
        )));
        if ($keywords === [] || $message === '') {
            return false;
        }

        $caseSensitive = (bool) ($trigger['case_sensitive'] ?? false);
        $wholeWord = (bool) ($trigger['whole_word'] ?? false);
        $matchType = $trigger['match_type'] ?? 'any';
        $text = $caseSensitive ? $message : mb_strtolower($message);
        $matches = [];

        foreach ($keywords as $keyword) {
            $needle = $caseSensitive ? $keyword : mb_strtolower($keyword);
            $matches[] = $wholeWord
                ? preg_match('/\b'.preg_quote($needle, '/').'\b/', $text) === 1
                : str_contains($text, $needle);
        }

        return $matchType === 'all'
            ? count(array_filter($matches)) === count($keywords)
            : count(array_filter($matches)) > 0;
    }

    protected function simulateFlowSteps(BotFlow $flow, string $message): array
    {
        $nodes = $flow->nodes->keyBy('id');
        $edgesByFrom = $flow->edges->groupBy('from_node_id');
        $incoming = $flow->edges->pluck('to_node_id')->map(fn ($id) => (int) $id)->flip();
        $start = $flow->nodes->first(fn (BotNode $node) => (bool) ($node->config['is_start'] ?? false))
            ?? $flow->nodes->first(fn (BotNode $node) => ! isset($incoming[(int) $node->id]))
            ?? $flow->nodes->sortBy('sort_order')->first();

        if (! $start) {
            return [['type' => 'warning', 'label' => 'No nodes found', 'detail' => 'This flow has no executable nodes.']];
        }

        $steps = [];
        $queue = [$start->id];
        $visited = [];

        while ($queue !== [] && count($steps) < 30) {
            $nodeId = array_shift($queue);
            if (! $nodeId || isset($visited[$nodeId])) {
                continue;
            }
            $visited[$nodeId] = true;
            $node = $nodes->get($nodeId);
            if (! $node) {
                continue;
            }

            $config = $node->config ?: [];
            $actionType = $config['action_type'] ?? $node->type;
            $passed = null;

            if ($node->type === 'condition') {
                $passed = $this->simulateCondition($config, $message);
                $steps[] = [
                    'node_id' => $node->id,
                    'type' => 'condition',
                    'label' => $config['type'] ?? 'condition',
                    'detail' => ($passed ? 'Matched' : 'Not matched').' against "'.$message.'"',
                ];
            } else {
                $steps[] = [
                    'node_id' => $node->id,
                    'type' => 'action',
                    'label' => str_replace('_', ' ', (string) $actionType),
                    'detail' => $this->simulateActionDetail($config),
                ];
            }

            $edges = $edgesByFrom->get($node->id, collect());
            if ($node->type === 'condition') {
                $targetLabel = $passed ? 'true' : 'false';
                $next = $edges->first(fn ($edge) => $edge->label === $targetLabel) ?? $edges->first();
                if ($next) {
                    $queue[] = $next->to_node_id;
                }

                continue;
            }

            foreach ($edges as $edge) {
                $queue[] = $edge->to_node_id;
            }
        }

        return $steps;
    }

    protected function simulateCondition(array $config, string $message): bool
    {
        $type = $config['type'] ?? 'text_contains';
        $value = (string) ($config['value'] ?? '');
        $pattern = (string) ($config['pattern'] ?? $value);
        $caseSensitive = (bool) ($config['case_sensitive'] ?? false);
        $text = $caseSensitive ? $message : mb_strtolower($message);
        $needle = $caseSensitive ? $value : mb_strtolower($value);

        return match ($type) {
            'text_equals' => $text === $needle,
            'text_starts_with' => str_starts_with($text, $needle),
            'regex_match' => $pattern !== '' && @preg_match($pattern, $message) === 1,
            default => $needle === '' || str_contains($text, $needle),
        };
    }

    protected function simulateActionDetail(array $config): string
    {
        return match ($config['action_type'] ?? 'send_text') {
            'send_text' => Str::limit((string) ($config['message'] ?? 'Send text'), 140),
            'send_buttons' => 'Send buttons: '.collect($config['buttons'] ?? [])->pluck('text')->join(', '),
            'send_media' => 'Send '.($config['media_type'] ?? 'media').': '.($config['media_url'] ?? 'missing URL'),
            'handoff' => 'Mark human handoff. Reason: '.($config['reason'] ?? 'handoff'),
            'add_tag' => 'Add tag: '.($config['tag_name'] ?? $config['tag_id'] ?? 'tag'),
            'assign_agent' => 'Assign human agent #'.($config['agent_id'] ?? 'not set'),
            default => str_replace('_', ' ', (string) ($config['action_type'] ?? 'action')),
        };
    }

    /**
     * Remove the specified bot.
     */
    public function destroy(Request $request, Bot $bot)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($bot->account_id, $account->id)) {
            abort(404);
        }

        try {
            DB::transaction(function () use ($bot, $account) {
                // If we are deleting the default bot, promote another bot (if any) so UI/runtime stays sane.
                if ($bot->is_default) {
                    $replacement = Bot::where('account_id', $account->id)
                        ->where('id', '!=', $bot->id)
                        ->orderBy('id')
                        ->first();

                    if ($replacement) {
                        $replacement->update(['is_default' => true]);
                    }
                }

                // Explicitly delete related records even though FK cascades should handle it.
                // This prevents "cannot delete" issues when constraints are missing/misconfigured in prod.
                $executionIds = BotExecution::where('bot_id', $bot->id)->pluck('id');
                if ($executionIds->isNotEmpty()) {
                    BotActionJob::whereIn('bot_execution_id', $executionIds)->delete();
                }

                BotExecution::where('bot_id', $bot->id)->delete();

                // Delete flows (and their nodes/edges via FK cascades, but delete edges first to avoid ordering issues).
                $flowIds = BotFlow::where('bot_id', $bot->id)->pluck('id');
                if ($flowIds->isNotEmpty()) {
                    // Edges depend on nodes; safest is to delete edges explicitly.
                    BotEdge::whereIn('bot_flow_id', $flowIds)->delete();
                    BotNode::whereIn('bot_flow_id', $flowIds)->delete();
                }
                BotFlow::where('bot_id', $bot->id)->delete();

                $bot->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to delete bot', [
                'bot_id' => $bot->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Unable to delete bot right now. Please try again.',
            ]);
        }

        return redirect()->route('app.chatbots.index')
            ->with('success', 'Chatbot deleted.');
    }
}
