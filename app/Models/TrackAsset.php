<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TrackAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'track_id', 'type', 'path', 'bitrate_kbps', 'segment_index', 'duration_ms'
    ];

    protected $casts = [
        'bitrate_kbps' => 'integer',
        'segment_index' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function getFullPath(): string
    {
        return Storage::disk('public')->path($this->path);
    }

    public function getUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function isPlaylist(): bool
    {
        return in_array($this->type, ['master', 'variant']);
    }

    public function isSegment(): bool
    {
        return $this->type === 'segment';
    }

    public function isWaveform(): bool
    {
        return $this->type === 'waveform';
    }
}
