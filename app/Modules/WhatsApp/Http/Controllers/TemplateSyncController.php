<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Services\TemplateSyncService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TemplateSyncController extends Controller
{
    public function __construct(
        protected TemplateSyncService $syncService
    ) {}

    /**
     * Sync templates from Meta for a connection.
     */
    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'connection_id' => 'required|exists:whatsapp_connections,id']);

        $connection = WhatsAppConnection::where('account_id', $account->id)
            ->findOrFail($validated['connection_id']);

        // Only owners/admins can sync
        Gate::authorize('update', $connection);

        try {
            $result = $this->syncService->sync($connection);

            return redirect()->back()->with('success', sprintf(
                'Synced %d templates (%d created, %d updated)',
                $result['total'],
                $result['created'],
                $result['updated']
            ))->with('sync_report', [
                'total' => $result['total'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors'] ?? []),
                'errors' => array_slice($result['errors'] ?? [], 0, 5),
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'sync' => 'Failed to sync templates: '.$e->getMessage()]);
        }
    }
}
