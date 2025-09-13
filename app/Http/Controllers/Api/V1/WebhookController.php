<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Jobs\SendWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function replay(Request $request, Webhook $webhook): JsonResponse
    {
        // Check if this is a transcode.completed webhook
        if ($webhook->type !== 'transcode.completed') {
            return response()->json([
                'message' => 'Only transcode.completed webhooks can be replayed',
            ], 400);
        }

        // Check if the webhook has a track_id in the payload
        $payload = $webhook->payload;
        if (!isset($payload['track_id'])) {
            return response()->json([
                'message' => 'Webhook payload does not contain track_id',
            ], 400);
        }

        // You might want to add authorization here to ensure only authorized users
        // can replay webhooks

        try {
            // Create a new webhook delivery job
            SendWebhookJob::dispatch($webhook);

            Log::info('Webhook replay requested', [
                'webhook_id' => $webhook->id,
                'track_id' => $payload['track_id'],
                'requested_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Webhook replay initiated',
                'webhook_id' => $webhook->id,
            ], 202);

        } catch (\Exception $e) {
            Log::error('Failed to initiate webhook replay', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to initiate webhook replay',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}