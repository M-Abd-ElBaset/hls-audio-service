<?php

namespace App\Jobs;

use App\Models\Track;
use App\Services\AudioTranscoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranscodeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600]; // Retry after 1, 5, 10 minutes

    public function __construct(public Track $track)
    {
    }

    public function handle(AudioTranscoder $transcoder): void
    {
        Log::info('Starting transcode job for track: ' . $this->track->id);
        
        $this->track->update(['status' => 'processing']);
        
        try {
            $success = $transcoder->transcode($this->track);
            
            if ($success) {
                $this->track->update(['status' => 'ready']);
                Log::info('Transcode completed for track: ' . $this->track->id);
                
                // Dispatch webhook job
                SendWebhookJob::dispatch($this->track);
            } else {
                $this->track->update([
                    'status' => 'failed',
                    'error_message' => 'Transcoding process failed'
                ]);
                Log::error('Transcode failed for track: ' . $this->track->id);
            }
        } catch (\Exception $e) {
            $this->track->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            Log::error('Transcode job failed: ' . $e->getMessage());
            throw $e; // Will trigger retry
        }
    }
    
    public function failed(\Throwable $exception): void
    {
        $this->track->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage()
        ]);
        Log::error('Transcode job failed after retries: ' . $exception->getMessage());
    }
}