<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun pengelola aplikasi ujian (admin / operator), disimpan di database
 * lokal "ujian". Guru dan siswa TIDAK disimpan di sini — keduanya login
 * memakai kredensial yang sudah ada di database "datacenter".
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPERATOR = 'operator';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_aktif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_aktif' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getNamaAttribute(): string
    {
        return (string) $this->name;
    }
}
