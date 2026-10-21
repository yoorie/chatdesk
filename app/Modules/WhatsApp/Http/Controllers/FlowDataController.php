<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppFlow;
use App\Modules\WhatsApp\Models\WhatsAppMetaEventLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FlowDataController extends Controller
{
    public function receive(Request $request, ?string $flow = null): JsonResponse
    {
        $resolvedFlow = $this->resolveFlow($flow, $request);

        if ($resolvedFlow && Schema::hasTable('whatsapp_meta_event_logs')) {
            WhatsAppMetaEventLog::create([
                'account_id' => $resolvedFlow->account_id,
                'whatsapp_connection_id' => $resolvedFlow->whatsapp_connection_id,
                'field' => 'flow_data',
                'event_type' => (string) ($request->input('action') ?: $request->input('flow_action') ?: 'data_exchange'),
                'object_id' => $resolvedFlow->meta_flow_id,
                'status' => 'received',
                'message' => 'WhatsApp Flow data exchange received.',
                'payload' => $request->all(),
                'received_at' => now(),
            ]);
        }

        $screen = $request->input('screen')
            ?? data_get($request->all(), 'flow_action_payload.screen')
            ?? data_get($request->all(), 'data.screen');

        return response()->json([
            'version' => (string) ($request->input('version') ?: '3.0'),
            'screen' => $screen,
            'data' => [
                'ok' => true,
                'received_at' => now()->toIso8601String(),
            ],
        ]);
    }

    protected function resolveFlow(?string $flow, Request $request): ?WhatsAppFlow
    {
        if (! Schema::hasTable('whatsapp_flows')) {
            return null;
        }

        $flowId = $flow
            ?: (string) ($request->input('flow_id') ?: $request->input('flow_token') ?: data_get($request->all(), 'data.flow_id'));

        if ($flowId === '') {
            return null;
        }

        return WhatsAppFlow::query()
            ->when(ctype_digit($flowId), fn ($query) => $query->where('id', (int) $flowId))
            ->when(! ctype_digit($flowId), fn ($query) => $query->where('meta_flow_id', $flowId))
            ->when(ctype_digit($flowId), fn ($query) => $query->orWhere('meta_flow_id', $flowId))
            ->first();
    }
}
