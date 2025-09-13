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
        Schema::create('playback_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable();
            $table->string('token_id');
            $table->string('ip');
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('closed_at')->nullable();
            
            $table->index(['track_id', 'last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playback_sessions');
    }
};
