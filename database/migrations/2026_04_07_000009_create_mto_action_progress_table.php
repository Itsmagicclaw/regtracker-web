<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mto_action_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mto_alert_id')->constrained('mto_alerts')->cascadeOnDelete();
            $table->foreignId('action_item_id')->constrained('action_items')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, in_progress, completed, skipped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable(); // MTO-added notes
            $table->timestamps();

            $table->index('mto_alert_id');
            $table->index('action_item_id');
            $table->index('status');
            $table->unique(['mto_alert_id', 'action_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mto_action_progress');
    }
};
