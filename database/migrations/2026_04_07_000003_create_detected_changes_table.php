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
            $table->foreignId('source_id')->constrained('regulatory_sources')->cascadeOnDelete();
            $table->timestamp('detected_at');
            $table->string('change_type');          // new_entry, delisted, metadata_change, list_update, fatf_greylist, fatf_blacklist
            $table->string('severity');             // critical, high, medium, low
            $table->string('title');
            $table->text('plain_english_summary');
            $table->text('raw_diff_content')->nullable();
            $table->json('affected_jurisdictions')->nullable();
            $table->json('affected_corridors')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('deadline')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('source_url')->nullable();
            $table->decimal('qa_confidence_score', 5, 2)->nullable();
            $table->string('qa_status')->default('pending'); // pending, admin_approved, auto_approved, dismissed
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('source_id');
            $table->index('detected_at');
            $table->index('severity');
            $table->index('qa_status');
            $table->index('change_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_changes');
    }
};
