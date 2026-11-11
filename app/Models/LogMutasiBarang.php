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

    public function reservationToolboxId(): ?string
    {
        $columnValue = $this->getAttribute('toolbox_id');

        if (is_string($columnValue) && $columnValue !== '') {
            return $columnValue;
        }

        if (preg_match('/\[toolbox:([0-9a-f-]{36})\]$/i', (string) $this->keterangan, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function displayKeterangan(): ?string
    {
        $description = $this->keterangan;

        if (! is_string($description)) {
            return null;
        }

        return preg_replace('/ \[toolbox:[0-9a-f-]{36}\]$/i', '', $description) ?: $description;
    }

    public static function keteranganWithToolbox(string $description, ?string $toolboxId): string
    {
        if ($toolboxId === null) {
            return mb_substr($description, 0, 255);
        }

        $marker = ' [toolbox:'.$toolboxId.']';

        return mb_substr($description, 0, 255 - mb_strlen($marker)).$marker;
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
