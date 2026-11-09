<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Colonnes supplémentaires propres à une maquette (au-delà des colonnes standard)
        Schema::create('maquette_colonnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquette_id')->constrained('maquettes')->cascadeOnDelete();
            $table->string('cle', 60);           // clé technique dans donnees_supplementaires
            $table->string('libelle');           // titre affiché dans le tableau
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['maquette_id', 'cle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquette_colonnes');
    }
};
