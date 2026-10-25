<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetailPeminjaman extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'detail_peminjaman';

    protected $primaryKey = 'detail_peminjaman_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'peminjaman_id',
        'barang_id',
        'jumlah_pinjam',
        'jumlah_kembali',
        'status',
        'tanggal_kembali',
        'kondisi_kembali',
        'catatan_kembali',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_pinjam' => 'integer',
            'jumlah_kembali' => 'integer',
            'tanggal_kembali' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class, 'peminjaman_id', 'peminjaman_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id', 'barang_id');
    }

    public function logMutasiBarangs(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'detail_peminjaman_id', 'detail_peminjaman_id');
    }
}
