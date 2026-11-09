<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeUniversitaire extends Model
{
    use HasPublicId;

    protected $table = 'annees_universitaires';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'is_courante'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'is_courante' => 'boolean',
        ];
    }

    public function scopeCourante(Builder $query): Builder
    {
        return $query->where('is_courante', true);
    }

    public function maquettes(): HasMany
    {
        return $this->hasMany(Maquette::class, 'annee_universitaire_id');
    }

    public function syllabus(): HasMany
    {
        return $this->hasMany(Syllabus::class, 'annee_universitaire_id');
    }
}
