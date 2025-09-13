<?php
// app/Services/HlsClipper.php

namespace App\Services;

use App\Models\Clip;
use App\Models\TrackAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class HlsClipper
{
    public function createClipPlaylist(Clip $clip): ?string
    {
        $track = $clip->track;
        
        // Get the best available variant (prefer higher bitrate)
        $variantAsset = $track->assets()
            ->where('type', 'variant')
            ->orderBy('bitrate_kbps', 'desc')
            ->first();
            
        if (!$variantAsset) {
            Log::error('No variant playlist found for track', ['track_id' => $track->id]);
            return null;
        }
        
        // Read and parse the variant playlist
        $variantContent = Storage::disk('public')->get($variantAsset->path);
        $parsedPlaylist = $this->parsePlaylist($variantContent);
        
        // Find segments within the clip time range
        $clipSegments = $this->findSegmentsInClipRange($parsedPlaylist, $clip);
        
        if (empty($clipSegments)) {
            Log::error('No segments found within clip time range', [
                'clip_id' => $clip->id,
                'start_ms' => $clip->start_ms,
                'end_ms' => $clip->end_ms,
            ]);
            return null;
        }
        
        // Generate the virtual playlist for the clip
        return $this->generateClipPlaylist($clipSegments, $variantAsset, $clip);
    }
    
    protected function parsePlaylist(string $playlistContent): array
    {
        $lines = explode("\n", trim($playlistContent));
        $parsed = [
            'version' => 3,
            'target_duration' => 6,
            'media_sequence' => 0,
            'playlist_type' => 'VOD',
            'segments' => [],
        ];
        
        $currentSegment = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // Parse playlist headers
            if (strpos($line, '#EXT-X-VERSION:') === 0) {
                $parsed['version'] = (int) str_replace('#EXT-X-VERSION:', '', $line);
            } elseif (strpos($line, '#EXT-X-TARGETDURATION:') === 0) {
                $parsed['target_duration'] = (int) str_replace('#EXT-X-TARGETDURATION:', '', $line);
            } elseif (strpos($line, '#EXT-X-MEDIA-SEQUENCE:') === 0) {
                $parsed['media_sequence'] = (int) str_replace('#EXT-X-MEDIA-SEQUENCE:', '', $line);
            } elseif (strpos($line, '#EXT-X-PLAYLIST-TYPE:') === 0) {
                $parsed['playlist_type'] = str_replace('#EXT-X-PLAYLIST-TYPE:', '', $line);
            }
            // Parse segment duration
            elseif (strpos($line, '#EXTINF:') === 0) {
                $durationPart = str_replace(['#EXTINF:', ','], '', $line);
                $duration = (float) $durationPart;
                
                $currentSegment = [
                    'duration' => $duration,
                    'url' => null,
                    'start_time' => 0,
                    'end_time' => 0,
                ];
            }
            // Parse segment URL
            elseif ($currentSegment !== null && !str_starts_with($line, '#')) {
                $currentSegment['url'] = $line;
                $parsed['segments'][] = $currentSegment;
                $currentSegment = null;
            }
        }
        
        // Calculate cumulative timings for each segment
        $currentTime = 0;
        foreach ($parsed['segments'] as &$segment) {
            $segment['start_time'] = $currentTime * 1000; // Convert to ms
            $segment['end_time'] = ($currentTime + $segment['duration']) * 1000; // Convert to ms
            $currentTime += $segment['duration'];
        }
        
        return $parsed;
    }
    
    protected function findSegmentsInClipRange(array $parsedPlaylist, Clip $clip): array
    {
        $clipSegments = [];
        $clipStartMs = $clip->start_ms;
        $clipEndMs = $clip->end_ms;
        
        foreach ($parsedPlaylist['segments'] as $segment) {
            $segmentStart = $segment['start_time'];
            $segmentEnd = $segment['end_time'];
            
            // Check if segment overlaps with clip
            if ($segmentEnd > $clipStartMs && $segmentStart < $clipEndMs) {
                $clipSegments[] = [
                    'url' => $segment['url'],
                    'duration' => $segment['duration'],
                    'start_time' => $segmentStart,
                    'end_time' => $segmentEnd,
                    'original_duration' => $segment['duration'],
                ];
            }
            
            // If we've passed the clip end, we can break early
            if ($segmentStart >= $clipEndMs) {
                break;
            }
        }
        
        return $clipSegments;
    }
    
    protected function generateClipPlaylist(array $clipSegments, TrackAsset $variantAsset, Clip $clip): string
    {
        $playlist = "#EXTM3U\n";
        $playlist .= "#EXT-X-VERSION:3\n";
        $playlist .= "#EXT-X-TARGETDURATION:6\n";
        $playlist .= "#EXT-X-MEDIA-SEQUENCE:0\n";
        $playlist .= "#EXT-X-PLAYLIST-TYPE:VOD\n";
        
        $totalDuration = 0;
        $clipStartMs = $clip->start_ms;
        $clipEndMs = $clip->end_ms;
        
        foreach ($clipSegments as $index => $segment) {
            $duration = $segment['duration'];
            $segmentStart = $segment['start_time'];
            $segmentEnd = $segment['end_time'];
            
            // Adjust first segment if it starts before the clip
            if ($index === 0 && $segmentStart < $clipStartMs) {
                $overlapMs = $clipStartMs - $segmentStart;
                $duration = max(0.001, $segment['duration'] - ($overlapMs / 1000));
            }
            
            // Adjust last segment if it ends after the clip
            if ($index === count($clipSegments) - 1 && $segmentEnd > $clipEndMs) {
                $overlapMs = $segmentEnd - $clipEndMs;
                $duration = max(0.001, $segment['duration'] - ($overlapMs / 1000));
            }
            
            $playlist .= sprintf("#EXTINF:%.3f,\n", $duration);
            $playlist .= $segment['url'] . "\n";
            
            $totalDuration += $duration;
        }
        
        $playlist .= "#EXT-X-ENDLIST\n";
        
        // Validate that the clip duration is reasonable
        $expectedDurationMs = $clipEndMs - $clipStartMs;
        $actualDurationMs = $totalDuration * 1000;
        $durationDiff = abs($expectedDurationMs - $actualDurationMs);
        
        if ($durationDiff > 500) { // Allow 500ms tolerance
            Log::warning('Clip duration mismatch', [
                'clip_id' => $clip->id,
                'expected_ms' => $expectedDurationMs,
                'actual_ms' => $actualDurationMs,
                'difference_ms' => $durationDiff,
            ]);
        }
        
        return $playlist;
    }
    
    public function getClipDuration(array $clipSegments, Clip $clip): float
    {
        $totalDuration = 0;
        $clipStartMs = $clip->start_ms;
        $clipEndMs = $clip->end_ms;
        
        foreach ($clipSegments as $index => $segment) {
            $duration = $segment['duration'];
            $segmentStart = $segment['start_time'];
            $segmentEnd = $segment['end_time'];
            
            // Adjust first segment
            if ($index === 0 && $segmentStart < $clipStartMs) {
                $overlapMs = $clipStartMs - $segmentStart;
                $duration = max(0.001, $segment['duration'] - ($overlapMs / 1000));
            }
            
            // Adjust last segment
            if ($index === count($clipSegments) - 1 && $segmentEnd > $clipEndMs) {
                $overlapMs = $segmentEnd - $clipEndMs;
                $duration = max(0.001, $segment['duration'] - ($overlapMs / 1000));
            }
            
            $totalDuration += $duration;
        }
        
        return $totalDuration;
    }
}