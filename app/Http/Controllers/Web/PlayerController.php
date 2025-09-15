<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\Clip;
use App\Services\SignedUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlayerController extends Controller
{
    public function showTrack(Request $request, Track $track)
    {
        $this->authorize('view', $track);

        $signedUrlGenerator = app(SignedUrlGenerator::class);
        $token = $signedUrlGenerator->generateTrackToken(
            $track, 
            $request->ip(),
            3600 // 1 hour expiration
        );

        $waveformAsset = $track->getWaveformAsset();
        $waveformData = null;
        
        if ($waveformAsset && Storage::disk('public')->exists($waveformAsset->path)) {
            $waveformData = json_decode(Storage::disk('public')->get($waveformAsset->path), true);
        }

        $signedPlaybackUrl = route('stream.track.master', [
            'track' => $track->uuid,
            'token' => $token
        ]);

        return view('player.track', compact('track', 'waveformData', 'signedPlaybackUrl'));
    }

    public function showClip(Request $request, Clip $clip)
    {
        $this->authorize('view', $clip);

        $track = $clip->track;
        $signedUrlGenerator = app(SignedUrlGenerator::class);
        $token = $signedUrlGenerator->generateClipToken(
            $clip, 
            $request->ip(),
            86400 // 24 hour expiration for clips
        );

        $waveformAsset = $track->getWaveformAsset();
        $waveformData = null;
        
        if ($waveformAsset && Storage::disk('public')->exists($waveformAsset->path)) {
            $waveformData = json_decode(Storage::disk('public')->get($waveformAsset->path), true);
        }

        $signedPlaybackUrl = route('stream.clip.master', [
            'clip' => $clip->uuid,
            'token' => $token
        ]);

        return view('player.clip', compact('clip', 'track', 'waveformData', 'signedPlaybackUrl'));
    }
}