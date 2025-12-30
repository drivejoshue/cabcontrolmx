<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1) Honeypot simple (campo “website” debe venir vacío)
        $honeypot = $request->input('website');
        if (!is_null($honeypot) && trim((string) $honeypot) !== '') {
            return response()->json(['message' => 'Rejected'], 422);
        }

        // 2) Header de llave pública
        $provided = $request->header('X-PUBLIC-KEY');
        $expected = (string) config('orbana.public_contact_key');

        if (!$expected || !hash_equals($expected, (string) $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
