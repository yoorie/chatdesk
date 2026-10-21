<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppFlow;
use App\Modules\WhatsApp\Services\MetaGraphService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlowController extends Controller
{
    public function __construct(
        protected MetaGraphService $metaGraph
    ) {}

    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        $connections = WhatsAppConnection::where('account_id', $account->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'waba_id', 'phone_number_id']);

        $flows = WhatsAppFlow::with('connection:id,name,slug')
            ->where('account_id', $account->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (WhatsAppFlow $flow) => [
                'id' => $flow->id,
                'meta_flow_id' => $flow->meta_flow_id,
                'name' => $flow->name,
                'status' => $flow->status,
                'category' => $flow->category,
                'json_version' => $flow->json_version,
                'data_api_version' => $flow->data_api_version,
                'data_channel_uri' => $flow->data_channel_uri,
                'validation_errors' => $flow->validation_errors ?? [],
                'last_synced_at' => $flow->last_synced_at?->toIso8601String(),
                'last_meta_error' => $flow->last_meta_error,
                'connection' => $flow->connection ? [
                    'id' => $flow->connection->id,
                    'name' => $flow->connection->name,
                    'slug' => $flow->connection->slug,
                ] : null,
            ]);

        return Inertia::render('WhatsApp/Flows/Index', [
            'connections' => $connections,
            'flows' => $flows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        $validated = $request->validate([
            'whatsapp_connection_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'data_channel_uri' => ['nullable', 'url', 'max:800'],
            'flow_json' => ['nullable', 'string'],
        ]);

        $connection = $this->connectionForAccount($account->id, (int) $validated['whatsapp_connection_id']);
        if (! $connection->waba_id || ! $connection->access_token) {
            return back()->with('error', 'Connect your WhatsApp account before creating WhatsApp Flows.');
        }

        try {
            $meta = $this->metaGraph->createFlow($connection->waba_id, $connection->access_token, [
                'name' => $validated['name'],
                'categories' => $validated['category'] ? [$validated['category']] : ['OTHER'],
                'data_channel_uri' => $validated['data_channel_uri'] ?? null,
            ]);

            $flowJson = $this->decodeFlowJson($validated['flow_json'] ?? null);
            if ($flowJson && ! empty($meta['id'])) {
                $this->metaGraph->updateFlowJson((string) $meta['id'], $connection->access_token, $flowJson);
            }

            $flow = WhatsAppFlow::updateOrCreate(
                ['account_id' => $account->id, 'meta_flow_id' => (string) ($meta['id'] ?? '')],
                [
                    'whatsapp_connection_id' => $connection->id,
                    'name' => $validated['name'],
                    'status' => strtolower((string) ($meta['status'] ?? 'draft')),
                    'category' => $validated['category'] ?? 'OTHER',
                    'data_channel_uri' => $validated['data_channel_uri'] ?? null,
                    'flow_json' => $flowJson,
                    'meta' => $meta,
                    'last_synced_at' => now(),
                    'last_meta_error' => null,
                ]
            );

            return back()->with('success', "Flow {$flow->name} created.");
        } catch (\Throwable $e) {
            return back()->with('error', 'We could not create this flow: '.\App\Support\CustomerMessage::humanize($e->getMessage()));
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        $connectionId = $request->integer('connection_id');

        $connections = WhatsAppConnection::where('account_id', $account->id)
            ->where('is_active', true)
            ->when($connectionId, fn ($query) => $query->where('id', $connectionId))
            ->get();

        $synced = 0;
        foreach ($connections as $connection) {
            if (! $connection->waba_id || ! $connection->access_token) {
                continue;
            }

            try {
                foreach ($this->metaGraph->listFlows($connection->waba_id, $connection->access_token) as $flow) {
                    WhatsAppFlow::updateOrCreate(
                        ['account_id' => $account->id, 'meta_flow_id' => (string) ($flow['id'] ?? '')],
                        [
                            'whatsapp_connection_id' => $connection->id,
                            'name' => $flow['name'] ?? 'Untitled Flow',
                            'status' => strtolower((string) ($flow['status'] ?? 'unknown')),
                            'category' => $flow['categories'][0] ?? null,
                            'json_version' => $flow['json_version'] ?? null,
                            'data_api_version' => $flow['data_api_version'] ?? null,
                            'data_channel_uri' => $flow['data_channel_uri'] ?? null,
                            'validation_errors' => $flow['validation_errors'] ?? [],
                            'meta' => $flow,
                            'last_synced_at' => now(),
                            'last_meta_error' => null,
                        ]
                    );
                    $synced++;
                }
            } catch (\Throwable $e) {
                $connection->forceFill(['webhook_last_error' => 'Flow sync failed: '.$e->getMessage()])->save();
            }
        }

        return back()->with('success', "Refreshed {$synced} flows from WhatsApp.");
    }

    public function update(Request $request, WhatsAppFlow $flow): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless(account_ids_match($flow->account_id, $account->id), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'data_channel_uri' => ['nullable', 'url', 'max:800'],
            'flow_json' => ['nullable', 'string'],
        ]);

        $flow->load('connection');
        try {
            if ($flow->meta_flow_id && $flow->connection?->access_token) {
                $this->metaGraph->updateFlowMetadata($flow->meta_flow_id, $flow->connection->access_token, $validated);
                $flowJson = $this->decodeFlowJson($validated['flow_json'] ?? null);
                if ($flowJson) {
                    $this->metaGraph->updateFlowJson($flow->meta_flow_id, $flow->connection->access_token, $flowJson);
                    $flow->flow_json = $flowJson;
                }
            }

            $flow->forceFill([
                'name' => $validated['name'],
                'category' => $validated['category'] ?? $flow->category,
                'data_channel_uri' => $validated['data_channel_uri'] ?? null,
                'last_synced_at' => now(),
                'last_meta_error' => null,
            ])->save();

            return back()->with('success', 'Flow updated.');
        } catch (\Throwable $e) {
            $flow->forceFill(['last_meta_error' => $e->getMessage()])->save();

            return back()->with('error', 'We could not update this flow: '.\App\Support\CustomerMessage::humanize($e->getMessage()));
        }
    }

    public function publish(Request $request, WhatsAppFlow $flow): RedirectResponse
    {
        return $this->flowAction($request, $flow, 'publish');
    }

    public function deprecate(Request $request, WhatsAppFlow $flow): RedirectResponse
    {
        return $this->flowAction($request, $flow, 'deprecate');
    }

    protected function flowAction(Request $request, WhatsAppFlow $flow, string $action): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless(account_ids_match($flow->account_id, $account->id), 404);

        $flow->load('connection');
        if (! $flow->meta_flow_id || ! $flow->connection?->access_token) {
            return back()->with('error', 'This flow has not been sent to WhatsApp yet. Save it first.');
        }

        try {
            $action === 'publish'
                ? $this->metaGraph->publishFlow($flow->meta_flow_id, $flow->connection->access_token)
                : $this->metaGraph->deprecateFlow($flow->meta_flow_id, $flow->connection->access_token);

            $fresh = $this->metaGraph->getFlow($flow->meta_flow_id, $flow->connection->access_token);
            $flow->forceFill([
                'status' => strtolower((string) ($fresh['status'] ?? ($action === 'publish' ? 'published' : 'deprecated'))),
                'validation_errors' => $fresh['validation_errors'] ?? [],
                'meta' => $fresh,
                'last_synced_at' => now(),
                'last_meta_error' => null,
            ])->save();

            return back()->with('success', $action === 'publish' ? 'Flow published.' : 'Flow retired.');
        } catch (\Throwable $e) {
            $flow->forceFill(['last_meta_error' => $e->getMessage()])->save();

            return back()->with('error', ($action === 'publish' ? 'We could not publish this flow: ' : 'We could not retire this flow: ').\App\Support\CustomerMessage::humanize($e->getMessage()));
        }
    }

    protected function connectionForAccount(int|string $accountId, int $connectionId): WhatsAppConnection
    {
        return WhatsAppConnection::where('account_id', $accountId)
            ->where('id', $connectionId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    protected function decodeFlowJson(?string $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Flow JSON must be valid JSON.');
        }

        return $decoded;
    }
}
