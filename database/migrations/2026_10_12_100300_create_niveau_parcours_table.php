<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Relation N-N : un parcours peut exister dans plusieurs niveaux
        Schema::create('niveau_parcours', function (Blueprint $table) {
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('parcours_id')->constrained('parcours')->cascadeOnDelete();
            $table->primary(['niveau_id', 'parcours_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveau_parcours');
    }
};
