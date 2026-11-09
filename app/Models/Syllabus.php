<?php

namespace App\Models;

use App\Enums\Statut;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Syllabus extends Model
{
    use HasPublicId;

    // Eloquent mettrait "syllabuses" au pluriel : on force le vrai nom de la table
    protected $table = 'syllabus';

    protected $fillable = [
        'ec_id', 'annee_universitaire_id', 'statut',
        'objectifs', 'prerequis', 'competences', 'resultats_apprentissage',
        'programme', 'methodes_pedagogiques', 'evaluation', 'bibliographie',
        'sections_supplementaires', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'statut' => Statut::class,
            'sections_supplementaires' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function ec(): BelongsTo
    {
        return $this->belongsTo(Ec::class, 'ec_id');
    }

    public function anneeUniversitaire(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class, 'annee_universitaire_id');
    }

    public function autorisations(): HasMany
    {
        return $this->hasMany(SyllabusAutorisation::class, 'syllabus_id');
    }

    /** Enseignants dont l'autorisation n'a pas été révoquée. */
    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'syllabus_autorisations', 'syllabus_id', 'user_id')
            ->wherePivotNull('revoque_le')
            ->withPivot('est_responsable', 'accorde_le');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublies(Builder $query): Builder
    {
        return $query->where('statut', Statut::Publie->value);
    }

    /**
     * Syllabus qu'un utilisateur a le droit de consulter :
     * - administrateur : tous ;
     * - enseignant : les publiés et ceux sur lesquels il a une autorisation active ;
     * - visiteur (null) : uniquement les publiés.
     */
    public function scopeVisiblesPour(Builder $query, ?User $user): Builder
    {
        if ($user?->estAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $condition) use ($user) {
            $condition->where('statut', Statut::Publie->value);

            if ($user !== null) {
                $condition->orWhereHas('autorisations', function (Builder $autorisation) use ($user) {
                    $autorisation->where('user_id', $user->id)->whereNull('revoque_le');
                });
            }
        });
    }

    /** Utilisé par la Policy : l'utilisateur a-t-il une autorisation active ? */
    public function estAutorisePour(User $user): bool
    {
        return $this->autorisations()
            ->where('user_id', $user->id)
            ->whereNull('revoque_le')
            ->exists();
    }
}
