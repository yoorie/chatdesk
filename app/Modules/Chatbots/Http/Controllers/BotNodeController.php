<?php

namespace App\Modules\Chatbots\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BotNodeController extends Controller
{
    /**
     * Store a newly created node.
     */
    public function store(Request $request, BotFlow $flow)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($flow->account_id, $account->id)) {
            abort(404);
        }

        $validated = $this->validateNode($request);

        $nextSortOrder = ($flow->nodes()->max('sort_order') ?? 0) + 1;

        BotNode::create([
            'account_id' => $account->id,
            'bot_flow_id' => $flow->id,
            'type' => $validated['type'],
            'config' => $validated['config'] ?? [],
            'sort_order' => $validated['sort_order'] ?? $nextSortOrder,
            'pos_x' => $validated['pos_x'] ?? null,
            'pos_y' => $validated['pos_y'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Step added.');
    }

    /**
     * Update the specified node.
     */
    public function update(Request $request, BotNode $node)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($node->account_id, $account->id)) {
            abort(404);
        }

        $validated = $this->validateNode($request, $node);

        $updates = [];
        if (array_key_exists('type', $validated)) {
            $updates['type'] = $validated['type'];
        }
        if (array_key_exists('config', $validated)) {
            $updates['config'] = $validated['config'];
        }
        if (array_key_exists('sort_order', $validated)) {
            $updates['sort_order'] = $validated['sort_order'];
        }
        if (array_key_exists('pos_x', $validated)) {
            $updates['pos_x'] = $validated['pos_x'];
        }
        if (array_key_exists('pos_y', $validated)) {
            $updates['pos_y'] = $validated['pos_y'];
        }

        if ($updates) {
            $node->update($updates);
        }

        return redirect()->back()->with('success', 'Step updated.');
    }

    /**
     * Remove the specified node.
     */
    public function destroy(Request $request, BotNode $node)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($node->account_id, $account->id)) {
            abort(404);
        }

        $node->delete();

        return redirect()->back()->with('success', 'Step deleted.');
    }

    protected function validateNode(Request $request, ?BotNode $node = null): array
    {
        $typeInput = $request->input('type');
        $type = $typeInput ?: ($node?->type ?? null);

        $validator = Validator::make($request->all(), [
            'type' => [
                $node ? 'sometimes' : 'required',
                'string',
                Rule::in(['condition', 'action', 'delay', 'webhook']),
            ],
            'config' => [
                $node ? 'sometimes' : 'required',
                'array',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:100000',
            ],
            'pos_x' => ['sometimes', 'integer'],
            'pos_y' => ['sometimes', 'integer'],
        ]);

        $validator->after(function ($validator) use ($request, $type) {
            if (! $request->has('config')) {
                return;
            }

            $config = $request->input('config', []);

            if ($type === 'action') {
                $actionType = $config['action_type'] ?? null;
                $allowed = ['send_text', 'send_template', 'send_buttons', 'send_list', 'send_media', 'send_flow', 'assign_agent', 'add_tag', 'add_segment', 'update_contact', 'create_deal', 'create_appointment', 'sync_integration', 'set_status', 'set_priority', 'handoff', 'send_payment_link', 'ai_agent_reply'];
                if (! $actionType || ! in_array($actionType, $allowed, true)) {
                    $validator->errors()->add('config.action_type', 'Invalid action type.');
                }

                if ($actionType === 'send_buttons') {
                    $buttons = $config['buttons'] ?? [];
                    if (! is_array($buttons) || count($buttons) < 1 || count($buttons) > 3) {
                        $validator->errors()->add('config.buttons', 'Buttons must be an array with 1 to 3 items.');
                    }
                    $bodyText = isset($config['body_text']) ? trim((string) $config['body_text']) : '';
                    if ($bodyText === '') {
                        $validator->errors()->add('config.body_text', 'Body text is required for send_buttons.');
                    }
                }

                if ($actionType === 'send_list') {
                    $listId = $config['list_id'] ?? null;
                    if (! is_numeric($listId) || (int) $listId <= 0) {
                        $validator->errors()->add('config.list_id', 'list_id is required for send_list.');
                    }
                }

                if ($actionType === 'send_payment_link') {
                    $paymentUrl = $config['payment_url'] ?? null;
                    if (empty($config['create_razorpay_link']) && (! $paymentUrl || ! filter_var($paymentUrl, FILTER_VALIDATE_URL))) {
                        $validator->errors()->add('config.payment_url', 'A valid payment URL is required.');
                    }
                }

                if ($actionType === 'send_media') {
                    $mediaUrl = $config['media_url'] ?? $config['url'] ?? null;
                    if (! $mediaUrl || ! filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                        $validator->errors()->add('config.media_url', 'A public media URL is required.');
                    }
                }

                if ($actionType === 'send_flow') {
                    $flowId = $config['flow_id'] ?? null;
                    $metaFlowId = isset($config['meta_flow_id']) ? trim((string) $config['meta_flow_id']) : '';
                    if ((! is_numeric($flowId) || (int) $flowId <= 0) && $metaFlowId === '') {
                        $validator->errors()->add('config.flow_id', 'WhatsApp Flow ID is required.');
                    }
                }

                if ($actionType === 'add_segment') {
                    $segmentId = $config['segment_id'] ?? null;
                    $segmentName = trim((string) ($config['segment_name'] ?? $config['segment'] ?? ''));
                    if ((! is_numeric($segmentId) || (int) $segmentId <= 0) && $segmentName === '') {
                        $validator->errors()->add('config.segment_id', 'Segment ID or name is required.');
                    }
                }

                if ($actionType === 'sync_integration' && trim((string) ($config['provider'] ?? '')) === '') {
                    $validator->errors()->add('config.provider', 'Integration provider is required.');
                }

                if ($actionType === 'create_deal' && trim((string) ($config['title'] ?? '')) === '') {
                    $validator->errors()->add('config.title', 'Deal title is required.');
                }
            }

            if ($type === 'condition') {
                $conditionType = $config['type'] ?? null;
                $allowed = ['text_contains', 'text_equals', 'text_starts_with', 'regex_match', 'time_window', 'connection_is', 'conversation_status', 'tags_contains'];
                if (! $conditionType || ! in_array($conditionType, $allowed, true)) {
                    $validator->errors()->add('config.type', 'Invalid condition type.');
                }
            }

            if ($type === 'delay') {
                $seconds = $config['seconds'] ?? null;
                if (! is_numeric($seconds) || (int) $seconds < 1) {
                    $validator->errors()->add('config.seconds', 'Delay seconds must be at least 1.');
                }
            }

            if ($type === 'webhook') {
                $url = $config['url'] ?? null;
                if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
                    $validator->errors()->add('config.url', 'Valid webhook URL required.');
                }
            }
        });

        return $validator->validate();
    }
}
