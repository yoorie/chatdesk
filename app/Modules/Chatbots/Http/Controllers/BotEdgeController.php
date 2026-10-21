<?php

namespace App\Modules\Chatbots\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotEdge;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class BotEdgeController extends Controller
{
    /**
     * Store a newly created edge.
     */
    public function store(Request $request, BotFlow $flow)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($flow->account_id, $account->id)) {
            abort(404);
        }

        $validated = Validator::make($request->all(), [
            'from_node_id' => 'required|integer|exists:bot_nodes,id',
            'to_node_id' => 'required|integer|exists:bot_nodes,id',
            'label' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ])->after(function ($validator) use ($request, $flow, $account) {
            $fromId = (int) $request->input('from_node_id');
            $toId = (int) $request->input('to_node_id');

            $fromNode = BotNode::whereKey($fromId)->first();
            $toNode = BotNode::whereKey($toId)->first();

            if (! $fromNode || ! $toNode) {
                return;
            }

            if (! account_ids_match($fromNode->account_id, $account->id) || ! account_ids_match($toNode->account_id, $account->id)) {
                $validator->errors()->add('from_node_id', 'Selected nodes do not belong to the current account.');

                return;
            }

            if ((int) $fromNode->bot_flow_id !== (int) $flow->id || (int) $toNode->bot_flow_id !== (int) $flow->id) {
                $validator->errors()->add('from_node_id', 'Selected nodes must belong to the same flow.');
            }
        })->validate();

        BotEdge::create([
            'account_id' => $account->id,
            'bot_flow_id' => $flow->id,
            'from_node_id' => $validated['from_node_id'],
            'to_node_id' => $validated['to_node_id'],
            'label' => $validated['label'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->back()->with('success', 'Steps connected.');
    }

    /**
     * Update the specified edge.
     */
    public function update(Request $request, BotEdge $edge)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($edge->account_id, $account->id)) {
            abort(404);
        }

        $validated = Validator::make($request->all(), [
            'from_node_id' => 'sometimes|required|integer|exists:bot_nodes,id',
            'to_node_id' => 'sometimes|required|integer|exists:bot_nodes,id',
            'label' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ])->after(function ($validator) use ($request, $edge, $account) {
            $fromId = (int) ($request->input('from_node_id', $edge->from_node_id));
            $toId = (int) ($request->input('to_node_id', $edge->to_node_id));

            $fromNode = BotNode::whereKey($fromId)->first();
            $toNode = BotNode::whereKey($toId)->first();
            if (! $fromNode || ! $toNode) {
                return;
            }

            if (! account_ids_match($fromNode->account_id, $account->id) || ! account_ids_match($toNode->account_id, $account->id)) {
                $validator->errors()->add('from_node_id', 'Selected nodes do not belong to the current account.');

                return;
            }

            if ((int) $fromNode->bot_flow_id !== (int) $edge->bot_flow_id || (int) $toNode->bot_flow_id !== (int) $edge->bot_flow_id) {
                $validator->errors()->add('from_node_id', 'Selected nodes must belong to the same flow.');
            }
        })->validate();

        $edge->update($validated);

        return redirect()->back()->with('success', 'Connection updated.');
    }

    /**
     * Remove the specified edge.
     */
    public function destroy(Request $request, BotEdge $edge)
    {
        $account = $request->attributes->get('account') ?? current_account();

        Gate::authorize('manage', [Bot::class, $account]);

        if (! account_ids_match($edge->account_id, $account->id)) {
            abort(404);
        }

        $edge->delete();

        return redirect()->back()->with('success', 'Connection removed.');
    }
}
