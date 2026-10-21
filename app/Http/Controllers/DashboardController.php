<?php

namespace App\Http\Controllers;

use App\Core\Modules\ModuleRegistry;
use App\Models\AccountUser;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Contacts\Models\ContactImportBatch;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected ModuleRegistry $moduleRegistry
    ) {}

    /**
     * Display the dashboard.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();
        $user = $request->user();

        $navigation = $this->moduleRegistry->getNavigationForAccount($account);

        $messages = app(\App\Services\DashboardMessageMetrics::class)->forAccount($account->id);

        $connections = WhatsAppConnection::where('account_id', $account->id)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END) as active', [true])->first();
        $totalConnections = (int) $connections->total;
        $activeConnections = (int) $connections->active;

        $templates = WhatsAppTemplate::where('account_id', $account->id)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN LOWER(TRIM(status)) IN ('rejected', 'disabled') THEN 1 ELSE 0 END) as rejected")->first();
        $totalTemplates = (int) $templates->total;
        $approvedTemplates = (int) $templates->approved;
        $rejectedTemplates = (int) $templates->rejected;

        $conversations = WhatsAppConversation::where('account_id', $account->id)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count, SUM(CASE WHEN assigned_to IS NOT NULL THEN 1 ELSE 0 END) as assigned")->first();
        $totalConversations = (int) $conversations->total;
        $openConversations = (int) $conversations->open_count;
        $assignedConversations = (int) $conversations->assigned;

        $members = AccountUser::where('account_id', $account->id)
            ->whereHas('user', fn ($query) => $query->where('is_platform_admin', false))
            ->when($account->owner_id, fn ($query) => $query->where('user_id', '!=', $account->owner_id))
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins")->first();
        $totalMembers = (int) $members->total + ($account->owner && ! $account->owner->isSuperAdmin() ? 1 : 0);
        $admins = (int) $members->admins;

        // Usage Statistics (from billing)
        $usageService = app(UsageService::class);
        $currentUsage = $usageService->getCurrentUsage($account);

        // Message trends use real message/status rows. Empty days remain zero.
        $trendStart = now()->subDays(29)->startOfDay();
        $trendRows = WhatsAppMessage::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw("SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound"),
            DB::raw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound"),
            DB::raw("SUM(CASE WHEN direction = 'outbound' AND status IN ('delivered', 'read') THEN 1 ELSE 0 END) as delivered"),
            DB::raw("SUM(CASE WHEN direction = 'outbound' AND status = 'read' THEN 1 ELSE 0 END) as read_count"),
            DB::raw("SUM(CASE WHEN direction = 'outbound' AND status = 'failed' THEN 1 ELSE 0 END) as failed")
        )
            ->where('account_id', $account->id)
            ->whereBetween('created_at', [$trendStart, now()])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $messageTrends = collect(range(0, 29))
            ->map(function ($item) use ($trendRows) {
                $date = now()->subDays(29 - $item)->toDateString();
                $row = $trendRows->get($date);

                return [
                    'date' => $date,
                    'count' => (int) ($row->count ?? 0),
                    'outbound' => (int) ($row->outbound ?? 0),
                    'inbound' => (int) ($row->inbound ?? 0),
                    'delivered' => (int) ($row->delivered ?? 0),
                    'read' => (int) ($row->read_count ?? 0),
                    'failed' => (int) ($row->failed ?? 0),
                ];
            });

        // Recent Conversations
        $recentConversations = WhatsAppConversation::where('account_id', $account->id)
            ->with(['contact'])
            ->orderBy('last_message_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'contact_name' => $conversation->contact?->name ?? $conversation->contact?->wa_id ?? 'Unknown',
                    'last_message' => $conversation->last_message_preview,
                    'status' => $conversation->status,
                    'last_activity_at' => $conversation->last_message_at?->toIso8601String()];
            });

        $activeCampaignIssues = Campaign::where('account_id', $account->id)
            ->whereIn('status', ['sending', 'paused', 'completed'])
            ->where('failed_count', '>', 0)
            ->count();
        $runningImports = ContactImportBatch::where('account_id', $account->id)
            ->whereIn('status', ['queued', 'processing'])
            ->count();

        $actionItems = collect([
            [
                'key' => 'open_inbox',
                'severity' => $openConversations > 0 ? 'warning' : 'success',
                'title' => $openConversations > 0 ? "{$openConversations} open conversations" : 'Inbox is clear',
                'body' => $openConversations > 0 ? 'Reply to waiting customers or assign chats to your team.' : 'No open conversation needs attention right now.',
                'count' => $openConversations,
                'href' => route('app.whatsapp.conversations.index'),
                'cta' => 'Open inbox',
            ],
            [
                'key' => 'campaign_failures',
                'severity' => $activeCampaignIssues > 0 ? 'danger' : 'success',
                'title' => $activeCampaignIssues > 0 ? "{$activeCampaignIssues} campaigns have unsent messages" : 'Campaigns are sending fine',
                'body' => $activeCampaignIssues > 0 ? 'Some messages did not go out. Open the campaign to see why and resend them.' : 'Every campaign message went out.',
                'count' => $activeCampaignIssues,
                'href' => route('app.broadcasts.index', ['status' => 'completed']),
                'cta' => 'Review campaigns',
            ],
            [
                'key' => 'template_rejections',
                'severity' => $rejectedTemplates > 0 ? 'warning' : 'success',
                'title' => $rejectedTemplates > 0 ? "{$rejectedTemplates} templates need review" : 'Templates ready',
                'body' => $rejectedTemplates > 0 ? 'WhatsApp rejected or paused some templates. Edit them before your next campaign.' : "{$approvedTemplates} approved templates are available.",
                'count' => $rejectedTemplates,
                'href' => route('app.whatsapp.templates.index'),
                'cta' => 'Open templates',
            ],
            [
                'key' => 'contact_imports',
                'severity' => $runningImports > 0 ? 'info' : 'success',
                'title' => $runningImports > 0 ? "{$runningImports} contact imports running" : 'No imports running',
                'body' => $runningImports > 0 ? 'Check import progress and any rows we skipped.' : 'No contact imports in progress.',
                'count' => $runningImports,
                'href' => route('app.contacts.index'),
                'cta' => 'Open contacts',
            ],
        ])->values();

        // Getting-started checklist. Every step completes itself from real workspace data.
        $contactsCount = WhatsAppContact::where('account_id', $account->id)->count();
        $campaignSent = Campaign::where('account_id', $account->id)
            ->where(function ($query) {
                $query->where('sent_count', '>', 0)
                    ->orWhereIn('status', ['sending', 'completed']);
            })
            ->exists();
        $setupSteps = [
            [
                'key' => 'connect_whatsapp',
                'title' => 'Connect WhatsApp',
                'body' => 'Link your business number.',
                'done' => $activeConnections > 0,
                'href' => $this->routeOrNull('app.whatsapp.connections.index'),
                'cta' => 'Connect WhatsApp',
            ],
            [
                'key' => 'add_contacts',
                'title' => 'Add contacts',
                'body' => 'Import a file or add them by hand.',
                'done' => $contactsCount > 0,
                'href' => $this->routeOrNull('app.contacts.index', ['import' => 1]),
                'cta' => 'Add contacts',
            ],
            [
                'key' => 'create_template',
                'title' => 'Create a template',
                'body' => 'Needed to message customers first.',
                'done' => $totalTemplates > 0,
                'href' => $this->routeOrNull('app.whatsapp.templates.index', ['panel' => 'create']),
                'cta' => 'Create template',
            ],
            [
                'key' => 'send_campaign',
                'title' => 'Send your first message',
                'body' => 'Pick a template and who gets it.',
                'done' => $campaignSent,
                'href' => $this->routeOrNull('app.broadcasts.index', ['panel' => 'create']),
                'cta' => 'Send a campaign',
            ],
        ];

        return Inertia::render('App/Dashboard', [
            'setup' => [
                'steps' => $setupSteps,
                'completed' => collect($setupSteps)->where('done', true)->count(),
                'total' => count($setupSteps),
            ],
            'account' => $account,
            'stats' => [
                'messages' => [
                    'total' => $messages['total'],
                    'today' => $messages['today'],
                    'this_week' => $messages['this_week'],
                    'this_month' => $messages['this_month'],
                    'outbound_this_month' => $messages['outbound_this_month'],
                    'inbound_this_month' => $messages['inbound_this_month'],
                    'outbound_today' => $messages['outbound_today'],
                    'inbound_today' => $messages['inbound_today'],
                    'delivered_this_month' => $messages['delivered_this_month'],
                    'read_this_month' => $messages['read_this_month'],
                    'failed_this_month' => $messages['failed_this_month'],
                    'inbound' => $messages['inbound'],
                    'outbound' => $messages['outbound'],
                    'delivered' => $messages['delivered'],
                    'read' => $messages['read'],
                    'failed' => $messages['failed']],
                'connections' => [
                    'total' => $totalConnections,
                    'active' => $activeConnections],
                'templates' => [
                    'total' => $totalTemplates,
                    'approved' => $approvedTemplates],
                'conversations' => [
                    'total' => $totalConversations,
                    'open' => $openConversations,
                    'assigned' => $assignedConversations],
                'team' => [
                    'total_members' => $totalMembers,
                    'admins' => $admins],
                'usage' => $currentUsage],
            'message_trends' => $messageTrends,
            'recent_conversations' => $recentConversations,
            'action_items' => $actionItems]);
    }

    private function routeOrNull(string $name, array $parameters = []): ?string
    {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $parameters) : null;
    }
}
