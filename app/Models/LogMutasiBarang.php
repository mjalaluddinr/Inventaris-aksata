<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogMutasiBarang extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'log_mutasi_barang';

    protected $primaryKey = 'log_mutasi_barang_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false; // Migrasi hanya memiliki 'created_at'

    protected $fillable = [
        'barang_id',
        'ruangan_id',
        'lemari_asal_id',
        'lemari_tujuan_id',
        'akun_id',
        'oleh_akun_id',
        'detail_peminjaman_id',
        'barang_dipakai_id',
        'log_input_id',
        'tipe',
        'jumlah',
        'stok_sebelum',
        'stok_sesudah',
        'keterangan',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'stok_sebelum' => 'integer',
            'stok_sesudah' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id', 'barang_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id', 'ruangan_id');
    }

    public function lemariAsal(): BelongsTo
    {
        return $this->belongsTo(Lemari::class, 'lemari_asal_id', 'lemari_id');
    }

    public function lemariTujuan(): BelongsTo
    {
        return $this->belongsTo(Lemari::class, 'lemari_tujuan_id', 'lemari_id');
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'akun_id', 'akun_id');
    }

    public function olehAkun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh_akun_id', 'akun_id');
    }

    public function detailPeminjaman(): BelongsTo
    {
        return $this->belongsTo(DetailPeminjaman::class, 'detail_peminjaman_id', 'detail_peminjaman_id');
    }

    public function barangDipakai(): BelongsTo
    {
        return $this->belongsTo(BarangDipakai::class, 'barang_dipakai_id', 'barang_dipakai_id');
    }

    public function logInput(): BelongsTo
    {
        return $this->belongsTo(LogInput::class, 'log_input_id', 'log_input_id');
    }
}
