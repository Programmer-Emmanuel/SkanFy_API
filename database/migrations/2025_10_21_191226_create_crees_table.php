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
        Schema::create('crees', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Clés étrangères UUID
            $table->uuid('admin_id');
            $table->uuid('qr_id');

            $table->foreign('admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('cascade');

            $table->foreign('qr_id')
                ->references('id')
                ->on('qrs')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crees');
    }
};
