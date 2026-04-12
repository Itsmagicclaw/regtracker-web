<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mto_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('mto_name');
            $table->string('primary_contact_name');
            $table->string('primary_contact_email');
            $table->json('license_jurisdictions'); // ["US", "UK", "CA", ...]
            $table->json('active_corridors'); // ["US-IN", "UK-PK", ...]
            $table->json('license_types'); // ["MSB", "MTL", ...]
            $table->string('notification_email');
            $table->string('notification_preference')->default('daily'); // instant, daily, weekly
            $table->boolean('created_by_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('notification_preference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mto_profiles');
    }
};
