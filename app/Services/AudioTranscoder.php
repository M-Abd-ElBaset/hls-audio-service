<?php

namespace App\Services;

use App\Models\Track;
use App\Models\TrackAsset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class AudioTranscoder
{
    public function transcode(Track $track): bool
    {
        $originalPath = Storage::disk('local')->path($track->original_path);
        $outputDir = 'tracks/' . $track->uuid;
        $outputPath = Storage::disk('public')->path($outputDir);
        
        // Create output directory
        if (!file_exists($outputPath)) {
            mkdir($outputPath, 0755, true);
        }
        
        // Create variants directory
        $variant64kPath = $outputPath . '/64k';
        $variant128kPath = $outputPath . '/128k';
        if (!file_exists($variant64kPath)) mkdir($variant64kPath, 0755);
        if (!file_exists($variant128kPath)) mkdir($variant128kPath, 0755);
        
        try {
            // Transcode to HLS variants
            $ffmpegCommand = [
                'ffmpeg', '-i', $originalPath,
                '-filter:a', 'loudnorm=I=-16:TP=-1.5:LRA=11',
                '-map', '0:a', '-c:a', 'aac', '-b:a', '64k', 
                '-hls_time', '6', '-hls_playlist_type', 'vod', 
                '-hls_segment_filename', $variant64kPath . '/seg-%05d.ts',
                $variant64kPath . '/index.m3u8',
                '-map', '0:a', '-c:a', 'aac', '-b:a', '128k', 
                '-hls_time', '6', '-hls_playlist_type', 'vod', 
                '-hls_segment_filename', $variant128kPath . '/seg-%05d.ts',
                $variant128kPath . '/index.m3u8',
                '-y' // Overwrite output files
            ];
            
            $process = new Process($ffmpegCommand);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();
            
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            
            // Create master playlist
            $masterPlaylist = "#EXTM3U\n";
            $masterPlaylist .= "#EXT-X-VERSION:3\n";
            $masterPlaylist .= "#EXT-X-STREAM-INF:BANDWIDTH=64000,CODECS=\"mp4a.40.2\"\n";
            $masterPlaylist .= "64k/index.m3u8\n";
            $masterPlaylist .= "#EXT-X-STREAM-INF:BANDWIDTH=128000,CODECS=\"mp4a.40.2\"\n";
            $masterPlaylist .= "128k/index.m3u8\n";
            
            file_put_contents($outputPath . '/master.m3u8', $masterPlaylist);
            
            // Generate waveform data
            $this->generateWaveform($track, $originalPath, $outputPath);
            
            // Save assets to database
            $this->saveTrackAssets($track, $outputDir);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Transcoding failed for track ' . $track->id, [
                'error' => $e->getMessage(),
                'command' => $ffmpegCommand ?? null
            ]);
            return false;
        }
    }
    
    private function generateWaveform(Track $track, string $inputPath, string $outputPath): void
    {
        // Generate waveform data using ffmpeg
        $waveformCommand = [
            'ffmpeg', '-i', $inputPath,
            '-filter_complex', 'aformat=channel_layouts=mono,showwavespic=s=1200x120:colors=white',
            '-frames:v', '1', 
            $outputPath . '/waveform.png',
            '-f', 's16le', '-ac', '1', '-ar', '200', '-',
            '2>/dev/null'
        ];
        
        $process = new Process($waveformCommand);
        $process->setTimeout(300);
        $process->run();
        
        if ($process->isSuccessful()) {
            // Process the raw PCM data to generate waveform peaks
            $output = $process->getOutput();
            $samples = unpack('s*', $output);
            
            $peaks = [];
            $sampleRateMs = 50; // 1 sample per 50ms
            $samplesPerPeak = 10; // Adjust based on your needs
            
            for ($i = 1; $i <= count($samples); $i += $samplesPerPeak) {
                $chunk = array_slice($samples, $i, $samplesPerPeak, true);
                if (!empty($chunk)) {
                    $max = max($chunk);
                    $min = min($chunk);
                    $peaks[] = [$min, $max];
                }
            }
            
            $waveformData = [
                'sample_rate_ms' => $sampleRateMs,
                'peaks' => $peaks
            ];
            
            file_put_contents($outputPath . '/waveform.json', json_encode($waveformData));
        }
    }
    
    private function saveTrackAssets(Track $track, string $outputDir): void
    {
        // Save master playlist
        TrackAsset::create([
            'track_id' => $track->id,
            'type' => 'master',
            'path' => $outputDir . '/master.m3u8',
            'bitrate_kbps' => null,
        ]);
        
        // Save variants
        TrackAsset::create([
            'track_id' => $track->id,
            'type' => 'variant',
            'path' => $outputDir . '/64k/index.m3u8',
            'bitrate_kbps' => 64,
        ]);
        
        TrackAsset::create([
            'track_id' => $track->id,
            'type' => 'variant',
            'path' => $outputDir . '/128k/index.m3u8',
            'bitrate_kbps' => 128,
        ]);
        
        // Save waveform
        TrackAsset::create([
            'track_id' => $track->id,
            'type' => 'waveform',
            'path' => $outputDir . '/waveform.json',
            'bitrate_kbps' => null,
        ]);
        
        // Save segments (scan the variant directories)
        $this->saveSegmentAssets($track, $outputDir . '/64k', 64);
        $this->saveSegmentAssets($track, $outputDir . '/128k', 128);
        
        // Get duration from the first variant playlist
        $variantPath = Storage::disk('public')->path($outputDir . '/64k/index.m3u8');
        if (file_exists($variantPath)) {
            $content = file_get_contents($variantPath);
            preg_match_all('/#EXTINF:([\d.]+)/', $content, $matches);
            
            if (!empty($matches[1])) {
                $totalDuration = array_sum($matches[1]) * 1000; // Convert to ms
                $track->update(['duration_ms' => (int) $totalDuration]);
            }
        }
    }
    
    private function saveSegmentAssets(Track $track, string $variantDir, int $bitrate): void
    {
        $variantPath = Storage::disk('public')->path($variantDir);
        $files = scandir($variantPath);
        
        foreach ($files as $file) {
            if (preg_match('/^seg-(\d+)\.ts$/', $file, $matches)) {
                $segmentIndex = (int) $matches[1];
                
                TrackAsset::create([
                    'track_id' => $track->id,
                    'type' => 'segment',
                    'path' => $variantDir . '/' . $file,
                    'bitrate_kbps' => $bitrate,
                    'segment_index' => $segmentIndex,
                ]);
            }
        }
    }
}