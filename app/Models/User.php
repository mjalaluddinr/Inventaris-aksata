<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'akun';

    protected $primaryKey = 'akun_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $passwordColumn = 'password_hash';

    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'password_hash',
        'role',
        'must_change_password',
        'is_active',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public function setPasswordHashAttribute(string $value): void
    {
        if (empty($value)) {
            return;
        }
        if (! Str::startsWith($value, ['$2y$', '$2a$', '$2b$'])) {
            $this->attributes['password_hash'] = bcrypt($value);
        } else {
            $this->attributes['password_hash'] = $value;
        }
    }

    public function privileges(): HasMany
    {
        return $this->hasMany(Privilege::class, 'akun_id', 'akun_id');
    }

    public function peminjamans(): HasMany
    {
        return $this->hasMany(Peminjaman::class, 'akun_id', 'akun_id');
    }

    public function diprosesPeminjamans(): HasMany
    {
        return $this->hasMany(Peminjaman::class, 'diproses_akun_id', 'akun_id');
    }

    public function barangDipakais(): HasMany
    {
        return $this->hasMany(BarangDipakai::class, 'akun_id', 'akun_id');
    }

    public function logMutasiBarangs(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'akun_id', 'akun_id');
    }

    public function logMutasiBarangsOleh(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'oleh_akun_id', 'akun_id');
    }

    private function hasHakAkses(string $hak): bool
    {
        if ($this->relationLoaded('privileges')) {
            return $this->getRelation('privileges')->contains('hak_akses', $hak);
        }

        return $this->privileges()->where('hak_akses', $hak)->exists();
    }

    public function canMelihat(): bool
    {
        return $this->hasHakAkses('melihat');
    }

    public function canManajemen(): bool
    {
        return $this->hasHakAkses('manajemen');
    }

    public function canMeminjamkan(): bool
    {
        return $this->hasHakAkses('meminjamkan');
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->role === 'admin'
            || ($this->role === 'laboran'
                && ($this->canMelihat() || $this->canManajemen() || $this->canMeminjamkan()));
    }

    public function canViewInventory(): bool
    {
        return $this->role === 'admin'
            || ($this->role === 'laboran' && ($this->canMelihat() || $this->canManajemen()));
    }

    public function canManageInventory(): bool
    {
        return $this->role === 'admin' || ($this->role === 'laboran' && $this->canManajemen());
    }

    public function canProcessLoans(): bool
    {
        return $this->role === 'admin' || ($this->role === 'laboran' && $this->canMeminjamkan());
    }

    public function canManageLaboranAccounts(): bool
    {
        return $this->role === 'admin' || ($this->role === 'laboran' && $this->canManajemen());
    }

    public function canManageAllAccounts(): bool
    {
        return $this->role === 'admin';
    }

    public function isLaboran(): bool
    {
        return $this->role === 'laboran' || $this->hasHakAkses('laboran');
    }

    public function getAccessFlags(): array
    {
        return [
            'can_melihat' => $this->canMelihat(),
            'can_manajemen' => $this->canManajemen(),
            'can_meminjamkan' => $this->canMeminjamkan(),
            'is_laboran' => $this->isLaboran(),
        ];
    }
}
