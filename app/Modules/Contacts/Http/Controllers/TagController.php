<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\ContactTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        $tags = ContactTag::where('account_id', $account->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'description' => $tag->description,
                'contacts_count' => $tag->contacts_count,
                'created_at' => $tag->created_at->toIso8601String(),
            ]);

        return Inertia::render('Contacts/Tags/Index', [
            'account' => $account,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contact_tags', 'name')->where('account_id', $account->id),
            ],
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        ContactTag::create([
            'account_id' => $account->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#3B82F6',
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('app.contacts.tags.index')->with('success', 'Tag created successfully.');
    }

    public function update(Request $request, ContactTag $tag)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($tag->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contact_tags', 'name')->where('account_id', $account->id)->ignore($tag->id),
            ],
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        $tag->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $tag->color,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('app.contacts.tags.index')->with('success', 'Tag updated successfully.');
    }

    public function destroy(Request $request, ContactTag $tag)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($tag->account_id, $account->id)) {
            abort(404);
        }

        try {
            // Be explicit to avoid FK surprises across environments.
            $tag->contacts()->detach();
            $tag->delete();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Unable to delete this tag right now. Please try again.');
        }

        return redirect()->route('app.contacts.tags.index')->with('success', 'Tag deleted successfully.');
    }
}
