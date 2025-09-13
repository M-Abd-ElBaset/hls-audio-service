<?php
// tests/Feature/WebhookTest.php

namespace Tests\Feature;

use App\Jobs\SendWebhookJob;
use App\Models\Track;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set webhook configuration for testing
        config([
            'streaming.webhooks.url' => 'https://webhook.example.com/transcode',
            'streaming.webhooks.secret' => 'test-secret',
            'streaming.webhooks.timeout' => 5,
        ]);
    }

    public function test_webhook_job_dispatched_after_successful_transcode()
    {
        Queue::fake();
        
        $track = Track::factory()->create(['status' => 'processing']);
        
        // Mock the transcoder service to return success
        $transcoder = $this->mock(\App\Services\AudioTranscoder::class);
        $transcoder->shouldReceive('transcode')
            ->with($track)
            ->andReturn(true);
        
        $job = new \App\Jobs\TranscodeAudioJob($track);
        $job->handle($transcoder);
        
        // Assert webhook job was dispatched
        Queue::assertPushed(SendWebhookJob::class, function ($job) use ($track) {
            return $job->track->id === $track->id;
        });
    }

    public function test_webhook_delivery_success()
    {
        Http::fake([
            'https://webhook.example.com/transcode' => Http::response(['success' => true], 200),
        ]);
        
        $track = Track::factory()->create();
        
        $job = new SendWebhookJob($track);
        $job->handle();
        
        // Assert webhook was created and marked as delivered
        $this->assertDatabaseHas('webhooks', [
            'type' => 'transcode.completed',
            'status_code' => 200,
        ]);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.example.com/transcode' &&
                   $request->hasHeader('X-Webhook-Signature') &&
                   $request->hasHeader('X-Webhook-Event');
        });
    }

    public function test_webhook_delivery_failure_retries()
    {
        Http::fake([
            'https://webhook.example.com/transcode' => Http::response(['error' => 'Server error'], 500),
        ]);
        
        $track = Track::factory()->create();
        
        $job = new SendWebhookJob($track);
        
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to throw exception for retry
        }
        
        // Assert webhook was created and marked as failed
        $this->assertDatabaseHas('webhooks', [
            'type' => 'transcode.completed',
            'status_code' => 500,
            'retry_count' => 1,
        ]);
    }

    public function test_webhook_replay_endpoint()
    {
        $webhook = Webhook::factory()->create([
            'type' => 'transcode.completed',
            'status_code' => 500,
        ]);
        
        $response = $this->postJson("/api/v1/webhooks/{$webhook->id}/replay");
        
        $response->assertStatus(202);
        $response->assertJson([
            'message' => 'Webhook replay initiated',
            'webhook_id' => $webhook->id,
        ]);
    }
}