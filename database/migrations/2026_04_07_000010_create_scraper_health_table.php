<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraper_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('regulatory_sources')->cascadeOnDelete();
            $table->string('status');              // ok, failed, warning
            $table->timestamp('run_at');
            $table->integer('run_duration_ms')->default(0);
            $table->integer('records_fetched')->default(0);
            $table->integer('changes_detected')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('source_id');
            $table->index('run_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_health');
    }
};
