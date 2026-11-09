<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semestre extends Model
{
    use HasPublicId;

    protected $table = 'semestres';

    protected $fillable = ['code', 'libelle', 'ordre'];

    public function unites(): HasMany
    {
        return $this->hasMany(UniteEnseignement::class, 'semestre_id');
    }
}
