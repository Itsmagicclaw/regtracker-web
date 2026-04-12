<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mto_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mto_profile_id')->constrained('mto_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('mto_profile_id');
            $table->index('is_active');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mto_users');
    }
};
