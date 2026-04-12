<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('regulatory_sources')->cascadeOnDelete();
            $table->longText('raw_content');
            $table->string('content_hash', 64); // SHA256 hash
            $table->bigInteger('file_size_bytes');
            $table->integer('record_count');
            $table->string('status')->default('ok'); // ok, error
            $table->timestamp('snapshot_at');
            $table->timestamps();

            $table->index('source_id');
            $table->index('content_hash');
            $table->unique(['source_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_snapshots');
    }
};
