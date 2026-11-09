<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ligne de maquette : un EC placé dans une UE, avec ses volumes horaires.
        // Les heures sont ici (et non dans ecs) car elles peuvent varier selon le parcours.
        Schema::create('ue_ec', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('ue_id')->constrained('unites_enseignement')->cascadeOnDelete();
            $table->foreignId('ec_id')->constrained('ecs')->restrictOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);

            $table->decimal('heures_cm', 6, 1)->nullable();
            $table->decimal('heures_td', 6, 1)->nullable();
            $table->decimal('heures_tp', 6, 1)->nullable();
            $table->decimal('heures_total', 6, 1)->nullable();
            $table->decimal('credits', 5, 1)->nullable();
            $table->decimal('coefficient', 5, 2)->nullable();
            $table->string('modalite_evaluation')->nullable();
            $table->json('donnees_supplementaires')->nullable();
            $table->timestamps();

            $table->unique(['ue_id', 'ec_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ue_ec');
    }
};
