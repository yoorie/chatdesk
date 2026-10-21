<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\ContactSegment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        $segments = ContactSegment::where('account_id', $account->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($seg) => [
                'id' => $seg->id,
                'name' => $seg->name,
                'description' => $seg->description,
                'contact_count' => $seg->contact_count,
                'last_calculated_at' => $seg->last_calculated_at?->toIso8601String(),
                'filters' => $seg->filters,
                'created_at' => $seg->created_at->toIso8601String(),
            ]);

        return Inertia::render('Contacts/Segments/Index', [
            'account' => $account,
            'segments' => $segments,
        ]);
    }

    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|string|max:100|in:'.implode(',', ContactSegment::allowedFilterFields()),
            'filters.*.operator' => 'required|string|in:equals,not_equals,contains,not_contains,starts_with,ends_with,greater_than,less_than,is_empty,is_not_empty',
            'filters.*.value' => 'nullable|string|max:500',
        ]);

        $segment = ContactSegment::create([
            'account_id' => $account->id,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'filters' => $validated['filters'] ?? [],
        ]);

        $segment->calculateContactCount();

        return redirect()->route('app.contacts.segments.index')->with('success', 'Segment created successfully.');
    }

    public function update(Request $request, ContactSegment $segment)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($segment->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|string|max:100|in:'.implode(',', ContactSegment::allowedFilterFields()),
            'filters.*.operator' => 'required|string|in:equals,not_equals,contains,not_contains,starts_with,ends_with,greater_than,less_than,is_empty,is_not_empty',
            'filters.*.value' => 'nullable|string|max:500',
        ]);

        $segment->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'filters' => $validated['filters'] ?? [],
        ]);

        $segment->calculateContactCount();

        return redirect()->route('app.contacts.segments.index', ['segment' => $segment->id])->with('success', 'Segment updated successfully.');
    }

    public function destroy(Request $request, ContactSegment $segment)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($segment->account_id, $account->id)) {
            abort(404);
        }

        try {
            // Be explicit to avoid FK surprises across environments.
            $segment->contacts()->detach();
            $segment->delete();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Unable to delete this segment right now. Please try again.');
        }

        return redirect()->route('app.contacts.segments.index')->with('success', 'Segment deleted successfully.');
    }

    public function recalculate(ContactSegment $segment)
    {
        $account = request()->attributes->get('account') ?? current_account();

        if (! account_ids_match($segment->account_id, $account->id)) {
            abort(404);
        }

        $segment->calculateContactCount();

        return back()->with('success', 'Segment count recalculated.');
    }
}
