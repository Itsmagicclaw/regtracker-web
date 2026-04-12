<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_name'); // e.g., "OFAC SDN List"
            $table->string('source_type'); // ofac, uk_sanctions, un_sanctions, eu_sanctions, dfat, austrac, fca, fintrac, federal_register
            $table->string('url');
            $table->text('description')->nullable();
            $table->integer('check_interval_hours')->default(24);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('source_type');
            $table->index('is_active');
            $table->index('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_sources');
    }
};
