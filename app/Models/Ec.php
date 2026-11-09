<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ec extends Model
{
    use HasPublicId;

    protected $table = 'ecs';

    protected $fillable = ['code', 'intitule', 'description'];

    /** Les lignes de maquette où cet EC apparaît (plusieurs parcours/semestres possibles). */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneMaquette::class, 'ec_id');
    }

    public function syllabus(): HasMany
    {
        return $this->hasMany(Syllabus::class, 'ec_id');
    }
}
