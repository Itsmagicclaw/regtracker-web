<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detected_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulatory_source_id')->constrained('regulatory_sources')->cascadeOnDelete();
            $table->string('change_type'); // new_entry, delisted, metadata_change, list_update
            $table->text('summary'); // AI-generated brief
            $table->text('detailed_description')->nullable();
            $table->string('severity'); // critical, high, medium, low
            $table->json('affected_jurisdictions')->nullable(); // ["US", "UK", ...]
            $table->json('affected_corridors')->nullable(); // ["US-IN", "UK-PK", ...]
            $table->string('qa_status')->default('pending'); // pending, approved, rejected
            $table->text('qa_notes')->nullable();
            $table->timestamp('qa_completed_at')->nullable();
            $table->boolean('requires_immediate_action')->default(false);
            $table->timestamps();

            $table->index('regulatory_source_id');
            $table->index('severity');
            $table->index('qa_status');
            $table->index('requires_immediate_action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_changes');
    }
};
