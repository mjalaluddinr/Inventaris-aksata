<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barang extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'barang';

    protected $primaryKey = 'barang_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kategori_id',
        'kode_barang',
        'nama_barang',
        'satuan',
        'stok_total',
        'stok_tersedia',
        'kondisi',
        'kelayakan',
        'spesifikasi',
    ];

    protected function casts(): array
    {
        return [
            'stok_total' => 'integer',
            'stok_tersedia' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id', 'kategori_id');
    }

    public function lokasiBarangs(): HasMany
    {
        return $this->hasMany(LokasiBarang::class, 'barang_id', 'barang_id');
    }

    public function detailPeminjamans(): HasMany
    {
        return $this->hasMany(DetailPeminjaman::class, 'barang_id', 'barang_id');
    }

    public function barangDipakais(): HasMany
    {
        return $this->hasMany(BarangDipakai::class, 'barang_id', 'barang_id');
    }

    public function logMutasiBarangs(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'barang_id', 'barang_id');
    }
}
