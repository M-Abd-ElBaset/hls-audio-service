<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRequests
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $limiter = match($type) {
            'uploads' => 'uploads',
            'playlist' => 'playlist',
            'segments' => 'segments',
            default => 'api',
        };

        $key = $limiter . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $this->getMaxAttempts($limiter))) {
            return response()->json([
                'message' => 'Too many attempts. Please try again later.'
            ], 429);
        }

        RateLimiter::hit($key, $this->getDecaySeconds($limiter));

        return $next($request);
    }

    protected function getMaxAttempts(string $type): int
    {
        return match($type) {
            'uploads' => 10,      // 10 uploads per hour
            'playlist' => 120,    // 120 playlist requests per minute
            'segments' => 1000,   // 1000 segment requests per minute
            default => 60,
        };
    }

    protected function getDecaySeconds(string $type): int
    {
        return match($type) {
            'uploads' => 3600,    // 1 hour
            'playlist' => 60,     // 1 minute
            'segments' => 60,     // 1 minute
            default => 60,
        };
    }
}