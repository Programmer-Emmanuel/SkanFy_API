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
        Schema::table('occasions', function (Blueprint $table) {
            // Vérifie d'abord si la colonne n'existe pas déjà (bonne pratique)
            if (!Schema::hasColumn('occasions', 'description')) {
                $table->string('description')->nullable()->after('nom_occasion');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('occasions', function (Blueprint $table) {
            //
        });
    }
};
