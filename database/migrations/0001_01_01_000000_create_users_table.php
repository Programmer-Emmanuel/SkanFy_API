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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom')->nullable();
            $table->string('tel_user')->unique()->nullable();
            $table->string('autre_tel')->unique()->nullable();
            $table->boolean('is_whatsapp')->default(false);
            $table->string('image_profil')->nullable();
            $table->string('email_user')->unique();
            $table->string('password');
            $table->string('otp', 6)->nullable(); 
            $table->timestamp('otp_expire_at')->nullable(); 
            $table->boolean('is_verify')->default(false);
            $table->integer('type_account');
            
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
