<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CsrfTokenController extends Controller
{
    /**
     * Refresh and return a new CSRF token.
     * This endpoint is used when the session expires and we need to refresh the token.
     * Works even when user is not authenticated, as long as session is valid.
     */
    public function refresh(Request $request)
    {
        // Check if session is valid
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            // If it's an Inertia request, return Inertia response
            if ($request->header('X-Inertia')) {
                return Inertia::location('/login');
            }

            // Otherwise return JSON
            return response()->json([
                'error' => 'Session expired.',
                'message' => 'Session expired.',
            ], 401);
        }

        // Regenerate the session to get a fresh CSRF token
        $request->session()->regenerateToken();

        // If it's an Inertia request, return Inertia response
        // Note: For CSRF refresh, we should NOT redirect Inertia requests as this endpoint
        // is meant to be called via axios, not Inertia. If an Inertia request hits this,
        // it means something went wrong, so redirect to home.
        if ($request->header('X-Inertia')) {
            return Inertia::location('/');
        }

        // Return JSON for axios requests
        return response()->json([
            'token' => csrf_token(),
        ]);
    }
}
