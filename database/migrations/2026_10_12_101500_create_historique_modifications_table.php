<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entite_type', 100);                    // ex. 'syllabus', 'maquette'
            $table->unsignedBigInteger('entite_id')->nullable();   // id interne (jamais exposé)
            $table->char('entite_public_id', 26)->nullable();      // id public, affiché dans l'API
            $table->string('action', 30);                          // creation, modification, publication...
            $table->string('description')->nullable();
            $table->json('anciennes_valeurs')->nullable();
            $table->json('nouvelles_valeurs')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entite_type', 'entite_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_modifications');
    }
};
