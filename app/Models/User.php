<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Maps onto the existing `users` table (unchanged from the Node version).
 *
 * Two things differ from a stock Laravel app and both matter:
 *   1. There is no `email_verified_at` and no `remember_token` column, so
 *      "remember me" is deliberately never used.
 *   2. `password` is NOT given the `hashed` cast — passwords are hashed
 *      explicitly with Hash::make() so behaviour stays identical to the
 *      Node/bcryptjs implementation (and existing hashes keep working).
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'phone',
        'country',
        'target_band',
        'language',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'target_band'   => 'float',
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public const ROLE_STUDENT = 'student';
    public const ROLE_ADMIN   = 'admin';

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** The shape the frontend receives — never includes the password hash. */
    public function toPublicArray(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'role'          => $this->role,
            'avatar'        => $this->avatar,
            'phone'         => $this->phone,
            'country'       => $this->country,
            'target_band'   => $this->target_band,
            'language'      => $this->language,
            'is_active'     => (bool) $this->is_active,
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'created_at'    => $this->created_at?->toDateTimeString(),
        ];
    }
}
