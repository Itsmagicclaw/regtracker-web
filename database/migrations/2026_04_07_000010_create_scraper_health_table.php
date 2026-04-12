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
            $table->string('scraper_name'); // ofac, uk_sanctions, un_sanctions, eu_sanctions, dfat, austrac, fca, fintrac, federal_register
            $table->timestamp('last_run_at')->nullable();
            $table->integer('last_run_duration_seconds')->nullable();
            $table->boolean('last_run_successful')->nullable();
            $table->text('last_error_message')->nullable();
            $table->integer('consecutive_failures')->default(0);
            $table->integer('total_records_processed')->default(0);
            $table->integer('changes_detected')->default(0);
            $table->integer('alerts_sent')->default(0);
            $table->boolean('is_healthy')->default(true);
            $table->timestamps();

            $table->index('scraper_name');
            $table->index('is_healthy');
            $table->unique('scraper_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_health');
    }
};
