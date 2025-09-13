<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'user_id', 'title', 'artist', 'duration_ms', 
        'status', 'original_path', 'error_message'
    ];

    protected $casts = [
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(TrackAsset::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }

    public function getMasterPlaylistAsset(): ?TrackAsset
    {
        return $this->assets()->where('type', 'master')->first();
    }

    public function getVariantAssets(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->assets()->where('type', 'variant')->get();
    }

    public function getWaveformAsset(): ?TrackAsset
    {
        return $this->assets()->where('type', 'waveform')->first();
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function getSegments(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->assets()->where('type', 'segment')->get();
    }

    public function getOriginalFileSize(): int
    {
        return Storage::disk('local')->size($this->original_path);
    }

    public function getOriginalMimeType(): string
    {
        return Storage::disk('local')->mimeType($this->original_path);
    }
}
