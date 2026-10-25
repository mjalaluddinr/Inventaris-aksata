<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ruangan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'ruangan';

    protected $primaryKey = 'ruangan_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_ruangan',
        'nama_ruangan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function lemaris(): HasMany
    {
        return $this->hasMany(Lemari::class, 'ruangan_id', 'ruangan_id');
    }

    public function lokasiBarangs(): HasMany
    {
        return $this->hasMany(LokasiBarang::class, 'ruangan_id', 'ruangan_id');
    }

    public function barangDipakais(): HasMany
    {
        return $this->hasMany(BarangDipakai::class, 'ruangan_id', 'ruangan_id');
    }

    public function logMutasiBarangs(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'ruangan_id', 'ruangan_id');
    }
}
