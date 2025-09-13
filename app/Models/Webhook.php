<?php
// app/Models/Webhook.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'payload', 'signature', 'status_code', 
        'retry_count', 'last_error', 'delivered_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'retry_count' => 'integer',
        'status_code' => 'integer',
    ];

    public function isDelivered(): bool
    {
        return !is_null($this->delivered_at) && $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isFailed(): bool
    {
        return is_null($this->delivered_at) || $this->status_code >= 400;
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    public function getPayloadTrackId(): ?int
    {
        return $this->payload['track_id'] ?? null;
    }
}