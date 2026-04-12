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
            $table->foreignId('change_id')->nullable()->constrained('detected_changes')->nullOnDelete();
            $table->string('list_source');         // ofac, uk_sanctions, fatf, fatf_blacklist, etc.
            $table->string('entry_type');           // individual, entity, country
            $table->string('primary_name');
            $table->json('aliases')->nullable();
            $table->date('date_added')->nullable();
            $table->date('date_removed')->nullable();
            $table->text('reason')->nullable();
            $table->json('raw_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('list_source');
            $table->index('entry_type');
            $table->index('is_active');
            $table->index('primary_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions_entries');
    }
};
