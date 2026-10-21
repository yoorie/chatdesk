<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();
        $filters = $request->validate([
            'severity' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:all,unread,read'],
        ]);

        $query = AppNotification::where('scope', 'workspace')
            ->where('account_id', $account->id)
            ->latest();

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (($filters['status'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        } elseif (($filters['status'] ?? 'all') === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->paginate(30)->withQueryString()->through(fn (AppNotification $notification) => $this->payload($notification));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'severity' => $filters['severity'] ?? '',
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'unread' => AppNotification::where('scope', 'workspace')->where('account_id', $account->id)->whereNull('read_at')->count(),
                'critical' => AppNotification::where('scope', 'workspace')->where('account_id', $account->id)->where('severity', 'critical')->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless((int) $notification->account_id === (int) $account->id && $notification->scope === 'workspace', 404);

        $notification->forceFill(['read_at' => now()])->save();

        return back()->with('success', 'Notification marked read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();

        AppNotification::where('scope', 'workspace')
            ->where('account_id', $account->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Notifications marked read.');
    }

    private function payload(AppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'severity' => $notification->severity,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->action_url,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
