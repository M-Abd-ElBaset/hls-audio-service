<?php

namespace App\Jobs;

use App\Models\Track;
use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 60, 300]; // Retry after 10, 60, 300 seconds

    public function __construct(public Track $track)
    {
    }

    public function handle(): void
    {
        $webhookUrl = config('streaming.webhooks.url');
        $webhookSecret = config('streaming.webhooks.secret');

        if (empty($webhookUrl) || empty($webhookSecret)) {
            Log::warning('Webhook URL or secret not configured, skipping webhook delivery');
            return;
        }

        // Prepare webhook payload
        $payload = $this->preparePayload();

        // Generate signature
        $signature = hash_hmac('sha256', json_encode($payload), $webhookSecret);

        // Create webhook record for logging
        $webhook = Webhook::create([
            'type' => 'transcode.completed',
            'payload' => $payload,
            'signature' => $signature,
            'status_code' => null,
            'retry_count' => 0,
            'last_error' => null,
        ]);

        try {
            $response = Http::timeout(config('streaming.webhooks.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => 'sha256=' . $signature,
                    'X-Webhook-Event' => 'transcode.completed',
                    'User-Agent' => 'AudioStreamingService/1.0',
                ])
                ->post($webhookUrl, $payload);

            $statusCode = $response->status();
            $webhook->update([
                'status_code' => $statusCode,
                'delivered_at' => now(),
            ]);

            if ($response->successful()) {
                Log::info('Webhook delivered successfully', [
                    'track_id' => $this->track->id,
                    'webhook_id' => $webhook->id,
                    'status_code' => $statusCode,
                ]);
            } else {
                Log::warning('Webhook delivery failed with status: ' . $statusCode, [
                    'track_id' => $this->track->id,
                    'webhook_id' => $webhook->id,
                    'response_body' => $response->body(),
                ]);

                // Throw exception to trigger retry
                throw new \Exception("Webhook delivery failed with status: {$statusCode}");
            }

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $webhook->update([
                'last_error' => $errorMessage,
                'retry_count' => $webhook->retry_count + 1,
            ]);

            Log::error('Webhook delivery failed: ' . $errorMessage, [
                'track_id' => $this->track->id,
                'webhook_id' => $webhook->id,
            ]);

            throw $e; // Will trigger retry
        }
    }

    protected function preparePayload(): array
    {
        $waveformAsset = $this->track->getWaveformAsset();
        $waveformData = null;

        if ($waveformAsset && $waveformAsset->isWaveform()) {
            $waveformContent = file_get_contents($waveformAsset->getFullPath());
            $waveformData = json_decode($waveformContent, true);
        }

        return [
            'track_id' => $this->track->id,
            'track_uuid' => $this->track->uuid,
            'title' => $this->track->title,
            'artist' => $this->track->artist,
            'duration_ms' => $this->track->duration_ms,
            'variants' => $this->track->getVariantAssets()->map(function ($variant) {
                return [
                    'bitrate_kbps' => $variant->bitrate_kbps,
                    'path' => $variant->path,
                    'url' => $variant->getUrl(),
                ];
            })->toArray(),
            'waveform_samples' => $waveformData['peaks'] ?? [],
            'waveform_sample_rate_ms' => $waveformData['sample_rate_ms'] ?? null,
            'created_at' => $this->track->created_at->toISOString(),
            'webhook_timestamp' => now()->toISOString(),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendWebhookJob failed after all retries', [
            'track_id' => $this->track->id,
            'error' => $exception->getMessage(),
        ]);

        // You might want to update the track status or send a notification
        // that webhook delivery failed after all retries
    }
}