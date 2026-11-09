<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Droit d'édition d'un enseignant sur un syllabus précis.
        // Un retrait remplit revoque_le (on garde la trace) ; un nouvel octroi le remet à null.
        Schema::create('syllabus_autorisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_id')->constrained('syllabus')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('est_responsable')->default(false);
            $table->foreignId('accorde_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accorde_le')->useCurrent();
            $table->timestamp('revoque_le')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus_autorisations');
    }
};
