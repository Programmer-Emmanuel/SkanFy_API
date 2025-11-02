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
        Schema::table('objets', function (Blueprint $table) {
            // Vérifie d'abord si la colonne n'existe pas déjà (bonne pratique)
            if (!Schema::hasColumn('objets', 'additional_info')) {
                $table->string('additional_info')->nullable()->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('objets', function (Blueprint $table) {
            if (Schema::hasColumn('objets', 'additional_info')) {
                $table->dropColumn('additional_info');
            }
        });
    }
};
