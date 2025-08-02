<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 100)->unique();
            $table->boolean('email_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('phone_verified')->default(false);
            $table->string('phone_verified_at')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('bio')->nullable();
            $table->string('gender')->nullable();
            $table->string('address')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('session_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_suspended')->default(false);
            $table->enum('role', ['customer'])->default('customer');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
