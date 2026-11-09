<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne de maquette : un EC placé dans une UE (table ue_ec). */
class LigneMaquette extends Model
{
    use HasPublicId;

    protected $table = 'ue_ec';

    protected $fillable = [
        'ue_id', 'ec_id', 'ordre',
        'heures_cm', 'heures_td', 'heures_tp', 'heures_total',
        'credits', 'coefficient', 'modalite_evaluation', 'donnees_supplementaires',
    ];

    protected function casts(): array
    {
        return [
            'heures_cm' => 'decimal:1',
            'heures_td' => 'decimal:1',
            'heures_tp' => 'decimal:1',
            'heures_total' => 'decimal:1',
            'credits' => 'decimal:1',
            'coefficient' => 'decimal:2',
            'donnees_supplementaires' => 'array',
        ];
    }

    public function ue(): BelongsTo
    {
        return $this->belongsTo(UniteEnseignement::class, 'ue_id');
    }

    public function ec(): BelongsTo
    {
        return $this->belongsTo(Ec::class, 'ec_id');
    }
}
