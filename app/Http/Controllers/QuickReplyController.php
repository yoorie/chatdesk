<?php

namespace App\Http\Controllers;

use App\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuickReplyController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();
        $search = trim((string) $request->query('search', ''));
        $type = in_array($request->query('type'), ['reply', 'button'], true)
            ? (string) $request->query('type')
            : 'all';

        $quickReplies = QuickReply::where('account_id', $account->id)
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('label', 'like', "%{$search}%")
                        ->orWhere('shortcut', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->get()
            ->map(fn (QuickReply $reply) => $this->replyPayload($reply));

        $allCounts = QuickReply::where('account_id', $account->id)
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return Inertia::render('QuickReplies/Index', [
            'quickReplies' => $quickReplies,
            'filters' => ['search' => $search, 'type' => $type],
            'stats' => [
                'total' => $quickReplies->count(),
                'active' => $quickReplies->where('is_active', true)->count(),
                'buttons' => (int) ($allCounts['button'] ?? 0),
                'replies' => (int) ($allCounts['reply'] ?? 0),
                'variables' => $quickReplies->filter(fn ($reply) => preg_match('/\{\{[a-z0-9_]+\}\}/i', $reply['message']))->count(),
                'uses' => $quickReplies->sum('usage_count'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();
        $validated = $this->validatedPayload($request, $account->id);

        $reply = QuickReply::create([
            ...$validated,
            'account_id' => $account->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->replyPayload($reply)], 201);
        }

        return back()->with('success', 'Quick reply created.');
    }

    public function update(Request $request, QuickReply $quickReply)
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless(account_ids_match($quickReply->account_id, $account->id), 404);

        $validated = $this->validatedPayload($request, $account->id, $quickReply->id);

        $quickReply->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->replyPayload($quickReply->fresh())]);
        }

        return back()->with('success', 'Quick reply updated.');
    }

    public function destroy(Request $request, QuickReply $quickReply)
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless(account_ids_match($quickReply->account_id, $account->id), 404);

        $quickReply->delete();

        return back()->with('success', 'Quick reply deleted.');
    }

    public function toggle(Request $request, QuickReply $quickReply)
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless(account_ids_match($quickReply->account_id, $account->id), 404);

        $quickReply->update([
            'is_active' => ! $quickReply->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $quickReply->is_active ? 'Quick reply enabled.' : 'Quick reply disabled.');
    }

    private function validatedPayload(Request $request, int $accountId, ?int $ignoreId = null): array
    {
        $label = trim((string) $request->input('label', ''));
        $shortcut = trim((string) $request->input('shortcut', ''));
        if ($shortcut === '') {
            $shortcut = QuickReply::shortcutFromLabel($label);
            $request->merge(['shortcut' => $shortcut]);
        }

        return $request->validate([
            'type' => ['sometimes', 'string', Rule::in(['reply', 'button'])],
            'label' => ['required', 'string', 'max:80'],
            'shortcut' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('quick_replies', 'shortcut')
                    ->where('account_id', $accountId)
                    ->ignore($ignoreId),
            ],
            'message' => ['required', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function replyPayload(QuickReply $reply): array
    {
        return [
            'id' => $reply->id,
            'type' => $reply->type ?: 'reply',
            'label' => $reply->label,
            'shortcut' => $reply->shortcut,
            'message' => $reply->message,
            'button_text' => \Illuminate\Support\Str::limit($reply->label, 20, ''),
            'is_active' => $reply->is_active,
            'usage_count' => $reply->usage_count,
            'last_used_at' => $reply->last_used_at?->toIso8601String(),
            'updated_at' => $reply->updated_at?->toIso8601String(),
        ];
    }
}
