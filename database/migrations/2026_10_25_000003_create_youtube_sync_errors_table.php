<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_sync_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_channel_id')->nullable()->constrained('youtube_channels')->nullOnDelete();
            $table->string('reason');
            $table->integer('http_code')->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_sync_errors');
    }
};
