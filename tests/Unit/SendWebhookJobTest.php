<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendWebhookJob;
use App\Models\Track;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWebhookJobTest extends TestCase
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

    public function test_webhook_payload_preparation()
    {
        $track = Track::factory()->create([
            'title' => 'Test Track',
            'artist' => 'Test Artist',
            'duration_ms' => 120000,
        ]);

        $job = new SendWebhookJob($track);
        
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('preparePayload');
        $method->setAccessible(true);
        
        $payload = $method->invoke($job);
        
        $this->assertEquals($track->id, $payload['track_id']);
        $this->assertEquals($track->uuid, $payload['track_uuid']);
        $this->assertEquals('Test Track', $payload['title']);
        $this->assertEquals('Test Artist', $payload['artist']);
        $this->assertEquals(120000, $payload['duration_ms']);
        $this->assertIsArray($payload['variants']);
        $this->assertIsArray($payload['waveform_samples']);
    }

    public function test_webhook_signature_generation()
    {
        Http::fake([
            'https://webhook.example.com/transcode' => Http::response(['success' => true], 200),
        ]);
        
        $track = Track::factory()->create();
        
        $job = new SendWebhookJob($track);
        $job->handle();
        
        // Get the created webhook
        $webhook = Webhook::first();
        
        $this->assertNotNull($webhook->signature);
        
        // Verify the signature is correct
        $expectedSignature = hash_hmac('sha256', json_encode($webhook->payload), 'test-secret');
        $this->assertEquals($expectedSignature, $webhook->signature);
    }

    public function test_webhook_headers_are_correct()
    {
        Http::fake([
            'https://webhook.example.com/transcode' => Http::response(['success' => true], 200),
        ]);
        
        $track = Track::factory()->create();
        
        $job = new SendWebhookJob($track);
        $job->handle();
        
        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('X-Webhook-Signature') &&
                   $request->hasHeader('X-Webhook-Event', 'transcode.completed') &&
                   $request->hasHeader('User-Agent');
        });
    }

    public function test_webhook_retry_mechanism()
    {
        Http::fake([
            'https://webhook.example.com/transcode' => Http::response(['error' => 'Server error'], 500),
        ]);
        
        $track = Track::factory()->create();
        
        $job = new SendWebhookJob($track);
        
        // First attempt should fail and trigger retry
        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Webhook delivery failed', $e->getMessage());
        }
        
        $webhook = Webhook::first();
        $this->assertEquals(1, $webhook->retry_count);
        $this->assertEquals(500, $webhook->status_code);
    }

    public function test_webhook_skipped_when_not_configured()
    {
        // Clear webhook configuration
        config([
            'streaming.webhooks.url' => null,
            'streaming.webhooks.secret' => null,
        ]);
        
        $track = Track::factory()->create();
        
        $job = new SendWebhookJob($track);
        $job->handle();
        
        // No webhook should be created when not configured
        $this->assertDatabaseCount('webhooks', 0);
    }
}