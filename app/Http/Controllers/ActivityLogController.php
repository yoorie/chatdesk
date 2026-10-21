<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Display account activity logs.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Aggregate activity from various sources
        $logs = collect();

        // Recent messages
        $recentMessages = WhatsAppMessage::where('account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($message) {
                return [
                    'id' => 'message_'.$message->id,
                    'type' => 'message',
                    'description' => ucfirst($message->direction).' message '.($message->status ?? 'sent'),
                    'metadata' => [
                        'message_id' => $message->id,
                        'direction' => $message->direction,
                        'status' => $message->status,
                        'type' => $message->type],
                    'created_at' => $message->created_at->toIso8601String()];
            });

        // Connection events
        $connectionEvents = WhatsAppConnection::where('account_id', $account->id)
            ->where(function ($query) {
                $query->whereNotNull('webhook_last_received_at')
                    ->orWhereNotNull('webhook_last_error');
            })
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($connection) {
                return [
                    'id' => 'connection_'.$connection->id,
                    'type' => $connection->webhook_last_error ? 'connection_error' : 'connection_success',
                    'description' => $connection->webhook_last_error
                        ? "Connection error: {$connection->name}"
                        : "Webhook received: {$connection->name}",
                    'metadata' => [
                        'connection_id' => $connection->id,
                        'connection_name' => $connection->name,
                        'error' => $connection->webhook_last_error],
                    'created_at' => ($connection->webhook_last_received_at ?? $connection->updated_at)->toIso8601String()];
            });

        $logs = $logs->merge($recentMessages)->merge($connectionEvents)
            ->sortByDesc('created_at')
            ->values()
            ->take(100);

        return Inertia::render('App/ActivityLogs/Index', [
            'account' => $account,
            'logs' => $logs]);
    }
}
