<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->unique();
            $table->string('handle')->nullable();
            $table->string('title');
            $table->string('uploads_playlist_id')->nullable();
            $table->nullableMorphs('owner');
            $table->boolean('is_active')->default(true);
            $table->string('sync_status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_channels');
    }
};
