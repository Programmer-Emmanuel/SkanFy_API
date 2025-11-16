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
        Schema::create('qrs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('is_active');
            $table->string('link_id')->nullable();
            $table->longText('image_qr')->nullable();
            $table->integer('generation')->default(0);

            $table->uuid('id_occasion')->nullable();
            $table->uuid('id_objet')->nullable();
            $table->uuid('id_user')->nullable();

            $table->foreign('id_occasion')
                ->references('id')
                ->on('occasions')
                ->onDelete('cascade');

            $table->foreign('id_objet')
                ->references('id')
                ->on('objets')
                ->onDelete('set null');


            $table->foreign('id_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qrs');
    }
};
