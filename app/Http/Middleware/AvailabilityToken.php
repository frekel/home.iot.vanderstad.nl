<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AvailabilityToken
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        abort_unless($request->isSecure(), 403, 'HTTPS is required.');
        $expected = config("availability.{$scope}_token");
        $other = config('availability.'.($scope === 'write' ? 'read' : 'write').'_token');
        abort_unless(is_string($expected) && strlen($expected) >= 32 && $expected !== $other, 503, 'Availability authentication is not configured.');
        $token = $request->bearerToken();
        abort_unless(is_string($token) && hash_equals($expected, $token), 401, 'Invalid availability token.');

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
