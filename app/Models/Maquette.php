<?php

namespace App\Models;

use App\Enums\Statut;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maquette extends Model
{
    use HasPublicId;

    protected $table = 'maquettes';

    protected $fillable = [
        'niveau_id', 'parcours_id', 'annee_universitaire_id', 'version',
        'titre', 'statut', 'notes', 'published_at', 'published_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'statut' => Statut::class,
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    public function parcours(): BelongsTo
    {
        return $this->belongsTo(Parcours::class, 'parcours_id');
    }

    public function anneeUniversitaire(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class, 'annee_universitaire_id');
    }

    public function colonnes(): HasMany
    {
        return $this->hasMany(MaquetteColonne::class, 'maquette_id')->orderBy('ordre');
    }

    public function unites(): HasMany
    {
        return $this->hasMany(UniteEnseignement::class, 'maquette_id')->orderBy('ordre');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopePubliees(Builder $query): Builder
    {
        return $query->where('statut', Statut::Publie->value);
    }

    /** Seul un brouillon est modifiable : une maquette publiée ou archivée est en lecture seule. */
    public function estModifiable(): bool
    {
        return $this->statut === Statut::Brouillon;
    }
}
