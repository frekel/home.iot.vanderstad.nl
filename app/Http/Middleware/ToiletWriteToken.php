<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ToiletWriteToken
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->isSecure(), 403, 'HTTPS is required.');

        $expected = config('toilet.write_token');
        abort_unless(is_string($expected) && strlen($expected) >= 32, 503, 'Toilet monitor authentication is not configured.');

        $provided = $request->bearerToken();
        abort_unless(is_string($provided) && hash_equals($expected, $provided), 401, 'Invalid toilet monitor token.');

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
