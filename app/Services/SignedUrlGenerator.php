<?php

namespace App\Services;

use App\Models\Clip;
use App\Models\Track;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SignedUrlGenerator
{
    public function generateTrackToken(Track $track, ?string $ip = null, ?int $expiresIn = 3600): string
    {
        $payload = [
            'track_id' => $track->id,
            'exp' => Carbon::now()->addSeconds($expiresIn)->getTimestamp(),
            'ip_claim' => $ip,
            'jti' => Str::random(16), // Unique token ID
        ];
        
        return Crypt::encrypt($payload);
    }
    
    public function generateClipToken(Clip $clip, ?string $ip = null, ?int $expiresIn = 3600): string
    {
        $payload = [
            'clip_id' => $clip->id,
            'exp' => Carbon::now()->addSeconds($expiresIn)->getTimestamp(),
            'ip_claim' => $ip,
            'jti' => Str::random(16),
        ];
        
        return Crypt::encrypt($payload);
    }
    
    public function validateToken(string $token): array
    {
        try {
            $payload = Crypt::decrypt($token);
            
            // Check expiration
            if (isset($payload['exp']) && Carbon::now()->getTimestamp() > $payload['exp']) {
                throw new \Exception('Token expired');
            }
            
            return $payload;
        } catch (\Exception $e) {
            throw new \Exception('Invalid token: ' . $e->getMessage());
        }
    }
}