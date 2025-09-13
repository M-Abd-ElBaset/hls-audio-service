<?php

namespace App\Http\Middleware;

use App\Services\SignedUrlGenerator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SignedStreamUrl
{
    public function __construct(protected SignedUrlGenerator $urlGenerator)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');
        
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }
        
        try {
            $payload = $this->urlGenerator->validateToken($token);
            
            // Validate IP claim if present
            if (isset($payload['ip_claim']) && $payload['ip_claim'] !== $request->ip()) {
                return response()->json(['error' => 'IP mismatch'], 403);
            }
            
            // Store payload in request for later use
            $request->attributes->set('token_payload', $payload);
            
            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 403);
        }
    }
}