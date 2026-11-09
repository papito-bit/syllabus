<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyllabusAutorisation extends Model
{
    protected $table = 'syllabus_autorisations';

    protected $fillable = [
        'syllabus_id', 'user_id', 'est_responsable',
        'accorde_par', 'accorde_le', 'revoque_le',
    ];

    protected function casts(): array
    {
        return [
            'est_responsable' => 'boolean',
            'accorde_le' => 'datetime',
            'revoque_le' => 'datetime',
        ];
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->whereNull('revoque_le');
    }

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(Syllabus::class, 'syllabus_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function accordePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accorde_par');
    }
}
