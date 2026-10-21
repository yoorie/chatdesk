<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log every Meta/WhatsApp webhook request to the default Laravel log
 * so you can see activity in storage/logs/laravel.log (grep "Meta-WhatsApp-Webhook").
 */
class LogMetaWhatsAppWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $method = $request->method();
        $ip = $request->ip();
        $connectionParam = $request->route('connection');

        Log::info('[Meta-WhatsApp-Webhook] Request received', [
            'method' => $method,
            'path' => $path,
            'connection' => $connectionParam,
            'ip' => $ip,
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'user_agent' => substr((string) $request->userAgent(), 0, 80),
        ]);

        $response = $next($request);

        Log::info('[Meta-WhatsApp-Webhook] Response sent', [
            'method' => $method,
            'path' => $path,
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
