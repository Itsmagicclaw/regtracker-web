<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulatory_source_id')->constrained('regulatory_sources')->cascadeOnDelete();
            $table->foreignId('detected_change_id')->nullable()->constrained('detected_changes')->cascadeOnDelete();
            $table->string('entry_type'); // individual, entity
            $table->string('primary_name');
            $table->json('aliases')->nullable(); // Alternative names as array
            $table->string('country')->nullable();
            $table->text('raw_entry_data'); // Store full original record
            $table->string('list_id'); // e.g., "OFAC-SDN", "UK-CONS"
            $table->string('external_reference_id')->nullable(); // ID from source
            $table->date('listed_date')->nullable();
            $table->date('delisted_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('regulatory_source_id');
            $table->index('detected_change_id');
            $table->index('primary_name');
            $table->index('entry_type');
            $table->index('is_active');
            $table->fullText('primary_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions_entries');
    }
};
