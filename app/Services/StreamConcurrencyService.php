<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class StreamConcurrencyService
{
    protected const SESSION_TTL = 30; // seconds
    protected const CONCURRENT_LIMIT = 2;
    
    public function canPlay(array $tokenPayload, int $trackId, string $ip): bool
    {
        $userId = $tokenPayload['user_id'] ?? null;
        $tokenId = $tokenPayload['jti'] ?? uniqid();
        
        $principal = $userId ? "user:{$userId}" : "ip:{$ip}";
        $key = "stream:{$principal}:{$trackId}:{$tokenId}";
        
        // Set or refresh the session
        Redis::setex($key, self::SESSION_TTL, 1);
        
        // Count active sessions for this principal and track
        $pattern = "stream:{$principal}:{$trackId}:*";
        $activeSessions = count(Redis::keys($pattern));
        
        if ($activeSessions > self::CONCURRENT_LIMIT) {
            Log::warning('Concurrent stream limit exceeded', [
                'principal' => $principal,
                'track_id' => $trackId,
                'active_sessions' => $activeSessions,
                'limit' => self::CONCURRENT_LIMIT,
            ]);
            return false;
        }
        
        return true;
    }
    
    public function cleanupExpiredSessions(): void
    {
        // Redis automatically expires keys with TTL, so no manual cleanup needed
        // This method is here for future expansion if needed
    }
}