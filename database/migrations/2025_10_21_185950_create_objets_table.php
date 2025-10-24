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
        Schema::create('objets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom_objet')->nullable();
            $table->string('image_objet')->nullable();
            $table->string('tel', 10)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objets');
    }
};
