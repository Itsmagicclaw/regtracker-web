<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_id')->constrained('detected_changes')->cascadeOnDelete();
            $table->integer('action_order'); // Sequential order for compliance
            $table->text('action_text'); // What the MTO must do
            $table->string('category'); // due_diligence, account_closure, reporting, verification
            $table->json('applies_to_jurisdictions')->nullable(); // ["US", "UK", ...] or null = all
            $table->json('applies_to_corridors')->nullable(); // ["US-IN", ...] or null = all
            $table->boolean('is_required')->default(true);
            $table->integer('deadline_days')->nullable(); // Days from change detection
            $table->timestamps();

            $table->index('change_id');
            $table->index('is_required');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
    }
};
