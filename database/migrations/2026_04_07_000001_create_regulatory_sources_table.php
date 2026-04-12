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
            $table->string('name');           // e.g., "OFAC SDN List"
            $table->string('type');           // sanctions_list, guidance, fatf
            $table->string('jurisdiction');   // GLOBAL, UK, EU, AU, USA, CA
            $table->string('source_url');
            $table->integer('check_frequency_hours')->default(24);
            $table->string('last_status')->default('pending'); // pending, ok, error
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
            $table->index('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_sources');
    }
};
