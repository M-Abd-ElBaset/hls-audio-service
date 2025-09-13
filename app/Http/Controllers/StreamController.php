<?php
// app/Http/Controllers/StreamController.php

namespace App\Http\Controllers;

use App\Models\Track;
use App\Models\Clip;
use App\Models\TrackAsset;
use App\Services\HlsClipper;
use App\Services\StreamConcurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class StreamController extends Controller
{
    public function trackMaster(Request $request, string $trackUuid): Response
    {
        $tokenPayload = $request->attributes->get('token_payload');
        $track = Track::where('uuid', $trackUuid)->firstOrFail();
        
        // Check concurrency limit
        $concurrencyService = app(StreamConcurrencyService::class);
        if (!$concurrencyService->canPlay($tokenPayload, $track->id, $request->ip())) {
            return response('Concurrent stream limit exceeded', 429);
        }
        
        $asset = $track->getMasterPlaylistAsset();
        if (!$asset) {
            return response('Master playlist not found', 404);
        }
        
        return $this->servePlaylist($asset);
    }
    
    public function trackVariant(Request $request, string $trackUuid, string $bitrate): Response
    {
        $tokenPayload = $request->attributes->get('token_payload');
        $track = Track::where('uuid', $trackUuid)->firstOrFail();
        
        // Check concurrency limit
        $concurrencyService = app(StreamConcurrencyService::class);
        if (!$concurrencyService->canPlay($tokenPayload, $track->id, $request->ip())) {
            return response('Concurrent stream limit exceeded', 429);
        }
        
        $asset = $track->assets()
            ->where('type', 'variant')
            ->where('bitrate_kbps', $bitrate)
            ->firstOrFail();
        
        return $this->servePlaylist($asset);
    }
    
    public function trackSegment(Request $request, string $trackUuid, string $bitrate, string $segment): Response
    {
        $tokenPayload = $request->attributes->get('token_payload');
        $track = Track::where('uuid', $trackUuid)->firstOrFail();
        
        // Check concurrency limit and update session
        $concurrencyService = app(StreamConcurrencyService::class);
        if (!$concurrencyService->canPlay($tokenPayload, $track->id, $request->ip())) {
            return response('Concurrent stream limit exceeded', 429);
        }
        
        $segmentIndex = (int) str_replace('seg-', '', $segment);
        $asset = $track->assets()
            ->where('type', 'segment')
            ->where('bitrate_kbps', $bitrate)
            ->where('segment_index', $segmentIndex)
            ->firstOrFail();
        
        return $this->serveSegment($asset);
    }
    
    public function clipMaster(Request $request, string $clipUuid): Response
    {
        $tokenPayload = $request->attributes->get('token_payload');
        $clip = Clip::where('uuid', $clipUuid)->firstOrFail();
        $track = $clip->track;
        
        // Check concurrency limit
        $concurrencyService = app(StreamConcurrencyService::class);
        if (!$concurrencyService->canPlay($tokenPayload, $track->id, $request->ip())) {
            return response('Concurrent stream limit exceeded', 429);
        }
        
        // Generate virtual playlist for the clip
        $clipper = app(HlsClipper::class);
        $playlistContent = $clipper->createClipPlaylist($clip);
        
        if (!$playlistContent) {
            return response('Failed to create clip playlist', 500);
        }
        
        return $this->serveContent($playlistContent, 'application/vnd.apple.mpegurl', 5);
    }
    
    protected function servePlaylist(TrackAsset $asset): Response
    {
        $content = Storage::disk('public')->get($asset->path);
        
        return $this->serveContent($content, 'application/vnd.apple.mpegurl', 5);
    }
    
    protected function serveSegment(TrackAsset $asset): Response
    {
        $content = Storage::disk('public')->get($asset->path);
        
        return $this->serveContent($content, 'video/MP2T', 31536000, true);
    }
    
    protected function serveContent(string $content, string $contentType, int $maxAge, bool $immutable = false): Response
    {
        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=' . $maxAge . ($immutable ? ', immutable' : ''),
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ];
        
        // Support for range requests (partial content)
        $response = response($content, 200, $headers);
        
        // Enable gzip compression for playlists
        if ($contentType === 'application/vnd.apple.mpegurl') {
            $response->header('Content-Encoding', 'gzip');
            $content = gzencode($content, 9);
        }
        
        return $response;
    }
}