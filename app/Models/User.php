<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, Notifiable;

    // SÉCURITÉ : "role" et "is_active" ne sont volontairement PAS assignables en masse.
    // Ils se modifient uniquement par du code explicite (forceFill) réservé aux admins.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function estAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function estEnseignant(): bool
    {
        return $this->role === Role::Enseignant;
    }

    /** Syllabus que cet utilisateur a le droit de modifier (autorisations non révoquées). */
    public function syllabusAutorises(): BelongsToMany
    {
        return $this->belongsToMany(Syllabus::class, 'syllabus_autorisations', 'user_id', 'syllabus_id')
            ->wherePivotNull('revoque_le')
            ->withPivot('est_responsable', 'accorde_le');
    }
}
