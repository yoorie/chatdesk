<?php

namespace App\Modules\Chatbots\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class BotFlowController extends Controller
{
    protected function validateFlowPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'trigger' => $isUpdate ? 'sometimes|required|array' : 'required|array',
            'trigger.type' => $isUpdate ? 'sometimes|required|string|in:inbound_message,keyword,button_reply,form_submission' : 'required|string|in:inbound_message,keyword,button_reply,form_submission',
            'enabled' => $isUpdate ? 'sometimes|boolean' : 'boolean',
            'priority' => $isUpdate ? 'sometimes|integer|min:0|max:1000' : 'integer|min:0|max:1000',
            'nodes' => $isUpdate ? 'sometimes|array' : 'nullable|array',
            'edges' => $isUpdate ? 'sometimes|array' : 'nullable|array',
        ];

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            $account = $request->attributes->get('account') ?? current_account();

            if ($request->has('trigger')) {
                $trigger = $request->input('trigger', []);
                if (! is_array($trigger)) {
                    $validator->errors()->add('trigger', 'Trigger must be an object.');
                } else {
                    $triggerType = $trigger['type'] ?? null;
                    if ($triggerType === 'keyword') {
                        $keywords = $trigger['keywords'] ?? [];
                        if (! is_array($keywords)) {
                            $validator->errors()->add('trigger.keywords', 'Keywords must be an array.');
                        } else {
                            $keywords = array_values(array_filter(array_map(
                                static fn ($keyword) => is_string($keyword) ? trim($keyword) : '',
                                $keywords
                            ), static fn ($keyword) => $keyword !== ''));
                            if (empty($keywords)) {
                                $validator->errors()->add('trigger.keywords', 'At least one keyword is required.');
                            }
                        }
                    }

                    if ($triggerType === 'button_reply') {
                        $buttonId = isset($trigger['button_id']) ? trim((string) $trigger['button_id']) : '';
                        if ($buttonId === '') {
                            $validator->errors()->add('trigger.button_id', 'Button reply ID is required.');
                        }
                    }

                    if ($triggerType === 'inbound_message' && isset($trigger['connection_ids'])) {
                        $connectionIds = $trigger['connection_ids'];
                        if (! is_array($connectionIds)) {
                            $validator->errors()->add('trigger.connection_ids', 'Connection IDs must be an array.');
                        } else {
                            $normalizedIds = array_values(array_unique(array_map(
                                static fn ($id) => (int) $id,
                                array_filter($connectionIds, static fn ($id) => is_numeric($id))
                            )));

                            if (! empty($normalizedIds)) {
                                $validCount = WhatsAppConnection::where('account_id', $account->id)
                                    ->whereIn('id', $normalizedIds)
                                    ->count();
                                if ($validCount !== count($normalizedIds)) {
                                    $validator->errors()->add('trigger.connection_ids', 'One or more trigger connections are invalid.');
                                }
                            }
                        }
                    }
                }
            }

            if ($request->has('nodes')) {
                $nodes = $request->input('nodes', []);
                if (! is_array($nodes)) {
                    $validator->errors()->add('nodes', 'Nodes must be an array.');

                    return;
                }

                $allowedNodeTypes = ['condition', 'action', 'delay', 'webhook'];
                $allowedActionTypes = ['send_text', 'send_template', 'send_buttons', 'send_list', 'send_media', 'send_flow', 'assign_agent', 'add_tag', 'add_segment', 'update_contact', 'create_deal', 'create_appointment', 'sync_integration', 'set_status', 'set_priority', 'handoff', 'send_payment_link', 'ai_agent_reply'];
                $allowedConditionTypes = ['text_contains', 'text_equals', 'text_starts_with', 'regex_match', 'time_window', 'connection_is', 'conversation_status', 'tags_contains'];

                foreach ($nodes as $i => $node) {
                    if (! is_array($node)) {
                        $validator->errors()->add("nodes.$i", 'Invalid node payload.');

                        continue;
                    }

                    if (array_key_exists('id', $node) && $node['id'] !== null && $node['id'] !== '' && ! is_numeric($node['id'])) {
                        $validator->errors()->add("nodes.$i.id", 'Node id must be numeric.');
                    }

                    if (array_key_exists('type', $node)) {
                        $type = $node['type'];
                        if (! is_string($type) || ! in_array($type, $allowedNodeTypes, true)) {
                            $validator->errors()->add("nodes.$i.type", 'Invalid node type.');

                            continue;
                        }

                        if (array_key_exists('config', $node) && ! is_array($node['config'])) {
                            $validator->errors()->add("nodes.$i.config", 'Node config must be an object.');

                            continue;
                        }

                        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

                        if ($type === 'action') {
                            $actionType = $config['action_type'] ?? null;
                            if (! $actionType || ! in_array($actionType, $allowedActionTypes, true)) {
                                $validator->errors()->add("nodes.$i.config.action_type", 'Invalid action type.');
                            }
                            if ($actionType === 'send_text') {
                                if (! array_key_exists('message', $config) || ! is_string($config['message'])) {
                                    $validator->errors()->add("nodes.$i.config.message", 'Message is required for send_text.');
                                }
                            }
                            if ($actionType === 'send_template') {
                                $templateId = $config['template_id'] ?? null;
                                if (! is_numeric($templateId) || (int) $templateId <= 0) {
                                    $validator->errors()->add("nodes.$i.config.template_id", 'Template ID is required for send_template.');
                                }
                            }
                            if ($actionType === 'send_buttons') {
                                $buttons = $config['buttons'] ?? [];
                                if (! is_array($buttons) || count($buttons) < 1 || count($buttons) > 3) {
                                    $validator->errors()->add("nodes.$i.config.buttons", 'Buttons must be an array with 1 to 3 items.');
                                }
                                $bodyText = isset($config['body_text']) ? trim((string) $config['body_text']) : '';
                                if ($bodyText === '') {
                                    $validator->errors()->add("nodes.$i.config.body_text", 'Body text is required for send_buttons.');
                                }
                            }
                            if ($actionType === 'send_list') {
                                $listId = $config['list_id'] ?? null;
                                if (! is_numeric($listId) || (int) $listId <= 0) {
                                    $validator->errors()->add("nodes.$i.config.list_id", 'list_id is required for send_list.');
                                }
                            }
                            if ($actionType === 'assign_agent') {
                                $agentId = $config['agent_id'] ?? null;
                                if (! is_numeric($agentId) || (int) $agentId <= 0) {
                                    $validator->errors()->add("nodes.$i.config.agent_id", 'Agent ID is required for assign_agent.');
                                }
                            }
                            if ($actionType === 'add_tag') {
                                $tagId = $config['tag_id'] ?? null;
                                $tagName = isset($config['tag']) ? trim((string) $config['tag']) : (isset($config['tag_name']) ? trim((string) $config['tag_name']) : '');
                                if ((! is_numeric($tagId) || (int) $tagId <= 0) && $tagName === '') {
                                    $validator->errors()->add("nodes.$i.config.tag_id", 'Tag ID or name is required for add_tag.');
                                }
                            }
                            if ($actionType === 'add_segment') {
                                $segmentId = $config['segment_id'] ?? null;
                                $segmentName = isset($config['segment']) ? trim((string) $config['segment']) : (isset($config['segment_name']) ? trim((string) $config['segment_name']) : '');
                                if ((! is_numeric($segmentId) || (int) $segmentId <= 0) && $segmentName === '') {
                                    $validator->errors()->add("nodes.$i.config.segment_id", 'Segment ID or name is required for add_segment.');
                                }
                            }
                            if ($actionType === 'send_flow') {
                                $flowId = $config['flow_id'] ?? null;
                                $metaFlowId = isset($config['meta_flow_id']) ? trim((string) $config['meta_flow_id']) : '';
                                if ((! is_numeric($flowId) || (int) $flowId <= 0) && $metaFlowId === '') {
                                    $validator->errors()->add("nodes.$i.config.flow_id", 'WhatsApp Flow ID is required for send_flow.');
                                }
                            }
                            if ($actionType === 'sync_integration') {
                                $provider = isset($config['provider']) ? trim((string) $config['provider']) : '';
                                if ($provider === '') {
                                    $validator->errors()->add("nodes.$i.config.provider", 'Integration provider is required for sync_integration.');
                                }
                            }
                        }

                        if ($type === 'condition') {
                            $conditionType = $config['type'] ?? null;
                            if (! $conditionType || ! in_array($conditionType, $allowedConditionTypes, true)) {
                                $validator->errors()->add("nodes.$i.config.type", 'Invalid condition type.');
                            }
                        }

                        if ($type === 'delay') {
                            $seconds = $config['seconds'] ?? null;
                            if (! is_numeric($seconds) || (int) $seconds < 1) {
                                $validator->errors()->add("nodes.$i.config.seconds", 'Delay seconds must be at least 1.');
                            }
                        }

                        if ($type === 'webhook') {
                            $url = $config['url'] ?? null;
                            if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
                                $validator->errors()->add("nodes.$i.config.url", 'Valid webhook URL required.');
                            }
                        }
                    }

                    if (array_key_exists('sort_order', $node) && $node['sort_order'] !== null && $node['sort_order'] !== '' && (! is_numeric($node['sort_order']) || (int) $node['sort_order'] < 0)) {
                        $validator->errors()->add("nodes.$i.sort_order", 'sort_order must be a positive integer.');
                    }
                    if (array_key_exists('pos_x', $node) && $node['pos_x'] !== null && $node['pos_x'] !== '' && ! is_numeric($node['pos_x'])) {
                        $validator->errors()->add("nodes.$i.pos_x", 'pos_x must be numeric.');
                    }
                    if (array_key_exists('pos_y', $node) && $node['pos_y'] !== null && $node['pos_y'] !== '' && ! is_numeric($node['pos_y'])) {
                        $validator->errors()->add("nodes.$i.pos_y", 'pos_y must be numeric.');
                    }
                }
            }

            if ($request->has('edges')) {
                $edges = $request->input('edges', []);
                if (! is_array($edges)) {
                    $validator->errors()->add('edges', 'Edges must be an array.');

                    return;
                }
                foreach ($edges as $i => $edge) {
                    if (! is_array($edge)) {
                        $validator->errors()->add("edges.$i", 'Invalid edge payload.');

                        continue;
                    }

                    foreach (['from_node_id', 'to_node_id'] as $k) {
                        if (array_key_exists($k, $edge) && $edge[$k] !== null && $edge[$k] !== '' && ! is_numeric($edge[$k])) {
                            $validator->errors()->add("edges.$i.$k", "$k must be numeric.");
                        }
                    }

                    if (array_key_exists('label', $edge) && $edge['label'] !== null && $edge['label'] !== '') {
                        if (! is_string($edge['label']) || strlen($edge['label']) > 50) {
                            $validator->errors()->add("edges.$i.label", 'Edge label must be a string up to 50 chars.');
                        }
                    }
                }
            }
        });

        return $validator->validate();
    }

    /**
     * Store a newly created flow.
     */
    public function store(Request $request, Bot $bot)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($bot->account_id, $account->id)) {
            abort(404);
        }

        $validated = $this->validateFlowPayload($request, false);

        $flow = BotFlow::create([
            'account_id' => $account->id,
            'bot_id' => $bot->id,
            'name' => $validated['name'],
            'trigger' => $validated['trigger'],
            'enabled' => $validated['enabled'] ?? true,
            'priority' => $validated['priority'] ?? 100]);

        $nodes = $validated['nodes'] ?? [];
        if (! empty($nodes)) {
            $sortOrder = 1;
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $flow->nodes()->create([
                    'account_id' => $account->id,
                    'type' => $node['type'] ?? 'action',
                    'config' => $node['config'] ?? [],
                    'sort_order' => $node['sort_order'] ?? $sortOrder,
                    'pos_x' => $node['pos_x'] ?? null,
                    'pos_y' => $node['pos_y'] ?? null,
                ]);
                $sortOrder++;
            }
        }

        $edges = $validated['edges'] ?? [];
        if (! empty($edges)) {
            $sortOrder = 1;
            foreach ($edges as $edge) {
                if (! is_array($edge)) {
                    continue;
                }
                $flow->edges()->create([
                    'account_id' => $account->id,
                    'from_node_id' => $edge['from_node_id'] ?? null,
                    'to_node_id' => $edge['to_node_id'] ?? null,
                    'label' => $edge['label'] ?? null,
                    'sort_order' => $edge['sort_order'] ?? $sortOrder,
                ]);
                $sortOrder++;
            }
        }

        return redirect()->back()->with('success', 'Flow created successfully.');
    }

    /**
     * Update the specified flow.
     */
    public function update(Request $request, BotFlow $flow)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($flow->account_id, $account->id)) {
            abort(404);
        }

        $validated = $this->validateFlowPayload($request, true);

        $updates = $validated;
        unset($updates['nodes'], $updates['edges']);

        if (! empty($updates)) {
            $flow->update($updates);
        }

        if (array_key_exists('nodes', $validated)) {
            $nodes = $validated['nodes'] ?? [];
            foreach ($nodes as $node) {
                if (! is_array($node) || empty($node['id'])) {
                    continue;
                }

                $existing = $flow->nodes()->whereKey($node['id'])->first();
                if (! $existing) {
                    continue;
                }

                $nodeUpdates = [];
                if (array_key_exists('type', $node)) {
                    $nodeUpdates['type'] = $node['type'];
                }
                if (array_key_exists('config', $node)) {
                    $nodeUpdates['config'] = $node['config'];
                }
                if (array_key_exists('sort_order', $node)) {
                    $nodeUpdates['sort_order'] = $node['sort_order'];
                }
                if (array_key_exists('pos_x', $node)) {
                    $nodeUpdates['pos_x'] = $node['pos_x'];
                }
                if (array_key_exists('pos_y', $node)) {
                    $nodeUpdates['pos_y'] = $node['pos_y'];
                }

                if ($nodeUpdates) {
                    $existing->update($nodeUpdates);
                }
            }
        }

        if (array_key_exists('edges', $validated)) {
            $flow->edges()->delete();
            $nodeIds = $flow->nodes()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $nodeIdSet = array_flip($nodeIds);
            $edges = $validated['edges'] ?? [];
            $sortOrder = 1;
            foreach ($edges as $edge) {
                if (! is_array($edge)) {
                    continue;
                }
                $fromId = isset($edge['from_node_id']) ? (int) $edge['from_node_id'] : null;
                $toId = isset($edge['to_node_id']) ? (int) $edge['to_node_id'] : null;
                if (! $fromId || ! $toId) {
                    continue;
                }
                if (! isset($nodeIdSet[$fromId]) || ! isset($nodeIdSet[$toId])) {
                    continue;
                }
                $flow->edges()->create([
                    'account_id' => $account->id,
                    'from_node_id' => $fromId,
                    'to_node_id' => $toId,
                    'label' => $edge['label'] ?? null,
                    'sort_order' => $edge['sort_order'] ?? $sortOrder,
                ]);
                $sortOrder++;
            }
        }

        return redirect()->back()->with('success', 'Flow updated successfully.');
    }

    /**
     * Remove the specified flow.
     */
    public function destroy(Request $request, BotFlow $flow)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($flow->account_id, $account->id)) {
            abort(404);
        }

        $flow->delete();

        return redirect()->back()->with('success', 'Flow deleted successfully.');
    }
}
