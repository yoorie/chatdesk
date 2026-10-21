<?php

namespace App\Http\Controllers;

use App\Models\QuickReply;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Contacts\Models\ContactSegment;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless($account, 404, 'Workspace not found.');

        $query = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->query('limit', 8), 3), 12);

        $results = collect($this->navigationResults($query));

        if (mb_strlen($query) >= 2) {
            $results = $results
                ->merge($this->contactResults($account->id, $query, $limit))
                ->merge($this->conversationResults($account->id, $query, $limit))
                ->merge($this->templateResults($account->id, $query, $limit))
                ->merge($this->campaignResults($account->id, $query, $limit))
                ->merge($this->segmentResults($account->id, $query, $limit))
                ->merge($this->quickReplyResults($account->id, $query, $limit))
                ->merge($this->connectionResults($account->id, $query, $limit));
        }

        return response()->json([
            'query' => $query,
            'results' => $results
                ->filter(fn ($item) => ! empty($item['href']))
                ->unique(fn ($item) => $item['type'].'|'.$item['id'])
                ->take(24)
                ->values(),
        ]);
    }

    protected function navigationResults(string $query): array
    {
        $items = [
            ['id' => 'dashboard', 'type' => 'page', 'label' => 'Dashboard', 'description' => 'Workspace performance and next actions', 'route' => 'app.dashboard'],
            ['id' => 'inbox', 'type' => 'page', 'label' => 'Inbox', 'description' => 'Customer conversations and replies', 'route' => 'app.whatsapp.conversations.index'],
            ['id' => 'templates', 'type' => 'page', 'label' => 'Templates', 'description' => 'Meta templates and template library', 'route' => 'app.whatsapp.templates.index'],
            ['id' => 'campaigns', 'type' => 'page', 'label' => 'Campaigns', 'description' => 'Broadcast campaigns and delivery', 'route' => 'app.broadcasts.index'],
            ['id' => 'contacts', 'type' => 'page', 'label' => 'Contacts', 'description' => 'Audience records, tags, and segments', 'route' => 'app.contacts.index'],
            ['id' => 'segments', 'type' => 'page', 'label' => 'Segments', 'description' => 'Dynamic contact audiences', 'route' => 'app.contacts.segments.index'],
            ['id' => 'billing', 'type' => 'page', 'label' => 'Billing', 'description' => 'Plan, wallet, usage, and invoices', 'route' => 'app.billing.index'],
            ['id' => 'waba', 'type' => 'page', 'label' => 'WABA Account', 'description' => 'WhatsApp Business API connection', 'route' => 'app.whatsapp.connections.index'],
            ['id' => 'developer', 'type' => 'page', 'label' => 'Developer', 'description' => 'API keys, webhooks, and usage ledger', 'route' => 'app.developer.index'],
            ['id' => 'support', 'type' => 'page', 'label' => 'Support', 'description' => 'Workspace support tickets', 'route' => 'app.support.index'],
            ['id' => 'settings', 'type' => 'page', 'label' => 'Settings', 'description' => 'Workspace and profile settings', 'route' => 'app.settings'],
        ];

        $needle = Str::lower($query);

        return collect($items)
            ->filter(fn ($item) => $needle === '' || Str::contains(Str::lower($item['label'].' '.$item['description']), $needle))
            ->take($needle === '' ? 6 : 10)
            ->map(fn ($item) => [
                'id' => $item['id'],
                'type' => $item['type'],
                'label' => $item['label'],
                'description' => $item['description'],
                'href' => $this->href($item['route']),
            ])
            ->all();
    }

    protected function contactResults(int $accountId, string $query, int $limit)
    {
        return WhatsAppContact::query()
            ->where('account_id', $accountId)
            ->where(function (Builder $builder) use ($query) {
                $this->likeAny($builder, $query, ['name', 'wa_id', 'phone', 'email', 'company']);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsAppContact $contact) => [
                'id' => 'contact-'.$contact->id,
                'type' => 'contact',
                'label' => $contact->name ?: $contact->wa_id ?: $contact->phone ?: 'Unnamed contact',
                'description' => trim(collect([$contact->phone ?: $contact->wa_id, $contact->email, $contact->company])->filter()->join(' · ')),
                'href' => $this->href('app.contacts.index', ['contact' => $contact->slug]),
            ]);
    }

    protected function conversationResults(int $accountId, string $query, int $limit)
    {
        return WhatsAppConversation::query()
            ->with('contact:id,name,wa_id,phone')
            ->where('account_id', $accountId)
            ->where(function (Builder $builder) use ($query) {
                $builder->where('last_message_preview', 'like', "%{$query}%")
                    ->orWhereHas('contact', function (Builder $contact) use ($query) {
                        $this->likeAny($contact, $query, ['name', 'wa_id', 'phone']);
                    });
            })
            ->latest('last_message_at')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsAppConversation $conversation) => [
                'id' => 'conversation-'.$conversation->id,
                'type' => 'conversation',
                'label' => $conversation->contact?->name ?: $conversation->contact?->wa_id ?: 'Conversation',
                'description' => $conversation->last_message_preview ?: 'Open inbox conversation',
                'href' => $this->href('app.whatsapp.conversations.index', ['conversation' => $conversation->id]),
            ]);
    }

    protected function templateResults(int $accountId, string $query, int $limit)
    {
        return WhatsAppTemplate::query()
            ->where('account_id', $accountId)
            ->where(function (Builder $builder) use ($query) {
                $this->likeAny($builder, $query, ['name', 'body_text', 'category', 'language', 'status']);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsAppTemplate $template) => [
                'id' => 'template-'.$template->id,
                'type' => 'template',
                'label' => $template->name,
                'description' => trim(collect([$template->category, $template->language, $template->status])->filter()->join(' · ')),
                'href' => $this->href('app.whatsapp.templates.index', ['template' => $template->slug]),
            ]);
    }

    protected function campaignResults(int $accountId, string $query, int $limit)
    {
        return Campaign::query()
            ->where('account_id', $accountId)
            ->where(function (Builder $builder) use ($query) {
                $this->likeAny($builder, $query, ['name', 'description', 'status', 'type']);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => 'campaign-'.$campaign->id,
                'type' => 'campaign',
                'label' => $campaign->name,
                'description' => trim(collect([$campaign->status, $campaign->total_recipients.' recipients'])->filter()->join(' · ')),
                'href' => $this->href('app.broadcasts.index', ['campaign' => $campaign->slug]),
            ]);
    }

    protected function segmentResults(int $accountId, string $query, int $limit)
    {
        return ContactSegment::query()
            ->where('account_id', $accountId)
            ->where(function (Builder $builder) use ($query) {
                $this->likeAny($builder, $query, ['name', 'description']);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ContactSegment $segment) => [
                'id' => 'segment-'.$segment->id,
                'type' => 'segment',
                'label' => $segment->name,
                'description' => ($segment->contact_count ?? 0).' contacts',
                'href' => $this->href('app.contacts.segments.index', ['segment' => $segment->id]),
            ]);
    }

    protected function quickReplyResults(int $accountId, string $query, int $limit)
    {
        return QuickReply::query()
            ->where('account_id', $accountId)
            ->where(function (Builder $builder) use ($query) {
                $this->likeAny($builder, $query, ['label', 'shortcut', 'message']);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (QuickReply $reply) => [
                'id' => 'quick-reply-'.$reply->id,
                'type' => 'quick_reply',
                'label' => $reply->label,
                'description' => '/'.$reply->shortcut.' · '.Str::limit($reply->message, 80),
                'href' => $this->href('app.quick-replies.index', ['search' => $reply->shortcut]),
            ]);
    }

    protected function connectionResults(int $accountId, string $query, int $limit)
    {
        return WhatsAppConnection::query()
            ->where('account_id', $accountId)
            ->where(function (Builder $builder) use ($query) {
                $this->likeAny($builder, $query, ['name', 'business_phone', 'waba_id', 'phone_number_id', 'meta_verified_name', 'meta_waba_name']);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsAppConnection $connection) => [
                'id' => 'connection-'.$connection->id,
                'type' => 'connection',
                'label' => $connection->meta_verified_name ?: $connection->name,
                'description' => trim(collect([$connection->business_phone, $connection->waba_id ? 'WABA '.$connection->waba_id : null])->filter()->join(' · ')),
                'href' => $this->href('app.whatsapp.connections.index'),
            ]);
    }


    protected function likeAny(Builder $builder, string $query, array $columns): void
    {
        foreach ($columns as $index => $column) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $builder->{$method}($column, 'like', "%{$query}%");
        }
    }

    protected function href(string $routeName, array $params = []): ?string
    {
        try {
            return route($routeName, $params);
        } catch (\Throwable) {
            return null;
        }
    }
}
