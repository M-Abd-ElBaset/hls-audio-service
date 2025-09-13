<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('track_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['master', 'variant', 'segment', 'waveform']);
            $table->string('path');
            $table->integer('bitrate_kbps')->nullable();
            $table->integer('segment_index')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
            
            $table->index(['track_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_assets');
    }
};
