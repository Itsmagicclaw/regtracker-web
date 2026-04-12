<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mto_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mto_id')->constrained('mto_profiles')->cascadeOnDelete();
            $table->foreignId('change_id')->constrained('detected_changes')->cascadeOnDelete();
            $table->timestamp('alerted_at');
            $table->string('alerted_via')->default('email'); // email, dashboard, both
            $table->timestamp('email_opened_at')->nullable();
            $table->timestamp('dashboard_viewed_at')->nullable();
            $table->timestamps();

            $table->index('mto_id');
            $table->index('change_id');
            $table->index('alerted_at');
            $table->unique(['mto_id', 'change_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mto_alerts');
    }
};
