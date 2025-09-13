<?php
// database/factories/WebhookFactory.php

namespace Database\Factories;

use App\Models\Track;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition()
    {
        $track = Track::factory()->create();

        return [
            'type' => 'transcode.completed',
            'payload' => [
                'track_id' => $track->id,
                'track_uuid' => $track->uuid,
                'title' => $this->faker->sentence,
                'artist' => $this->faker->name,
                'duration_ms' => $this->faker->numberBetween(1000, 300000),
                'variants' => [
                    [
                        'bitrate_kbps' => 64,
                        'path' => 'tracks/' . $track->uuid . '/64k/index.m3u8',
                        'url' => 'http://example.com/storage/tracks/' . $track->uuid . '/64k/index.m3u8',
                    ],
                    [
                        'bitrate_kbps' => 128,
                        'path' => 'tracks/' . $track->uuid . '/128k/index.m3u8',
                        'url' => 'http://example.com/storage/tracks/' . $track->uuid . '/128k/index.m3u8',
                    ]
                ],
                'waveform_samples' => [],
                'waveform_sample_rate_ms' => 50,
                'created_at' => now()->toISOString(),
                'webhook_timestamp' => now()->toISOString(),
            ],
            'signature' => $this->faker->sha256,
            'status_code' => $this->faker->randomElement([200, 201, 202, 400, 500, null]),
            'retry_count' => $this->faker->numberBetween(0, 3),
            'last_error' => $this->faker->optional(0.3)->sentence,
            'delivered_at' => $this->faker->optional(0.7)->dateTime,
        ];
    }

    public function delivered()
    {
        return $this->state(function (array $attributes) {
            return [
                'status_code' => 200,
                'delivered_at' => now(),
                'last_error' => null,
            ];
        });
    }

    public function failed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status_code' => 500,
                'delivered_at' => null,
                'last_error' => 'Connection timeout',
                'retry_count' => 3,
            ];
        });
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status_code' => null,
                'delivered_at' => null,
                'last_error' => null,
                'retry_count' => 0,
            ];
        });
    }
}