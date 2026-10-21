<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestCorrelationId
{
    public const REQUEST_ID_ATTRIBUTE = 'request_id';

    public const REQUEST_ID_HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header(self::REQUEST_ID_HEADER);
        if (empty($id) || ! is_string($id) || strlen($id) > 128) {
            $id = 'req_'.Str::uuid()->toString();
        }

        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $id);

        $response = $next($request);

        if ($response instanceof Response && method_exists($response, 'headers')) {
            $response->headers->set(self::REQUEST_ID_HEADER, $id);
        }

        return $response;
    }
}
