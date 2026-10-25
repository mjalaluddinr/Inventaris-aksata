<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peminjaman extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'peminjaman';

    protected $primaryKey = 'peminjaman_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'akun_id',
        'diproses_akun_id',
        'kode_peminjaman',
        'jenis_peminjaman',
        'keperluan',
        'tanggal_pengajuan',
        'tanggal_pinjam',
        'tanggal_rencana_kembali',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pengajuan' => 'datetime',
            'tanggal_pinjam' => 'datetime',
            'tanggal_rencana_kembali' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'akun_id', 'akun_id');
    }

    public function diprosesAkun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_akun_id', 'akun_id');
    }

    public function detailPeminjamans(): HasMany
    {
        return $this->hasMany(DetailPeminjaman::class, 'peminjaman_id', 'peminjaman_id');
    }
}
