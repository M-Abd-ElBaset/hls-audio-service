<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clip extends Model
{
     use HasFactory;

    protected $fillable = [
        'uuid', 'track_id', 'user_id', 'start_ms', 'end_ms', 'name'
    ];

    protected $casts = [
        'start_ms' => 'integer',
        'end_ms' => 'integer',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDurationMs(): int
    {
        return $this->end_ms - $this->start_ms;
    }
}
