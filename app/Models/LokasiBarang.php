<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LokasiBarang extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lokasi_barang';

    protected $primaryKey = 'lokasi_barang_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'barang_id',
        'ruangan_id',
        'lemari_id',
        'toolbox_id',
        'stok_total',
        'stok_tersedia',
    ];

    protected function casts(): array
    {
        return [
            'stok_total' => 'integer',
            'stok_tersedia' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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

    public function lemari(): BelongsTo
    {
        return $this->belongsTo(Lemari::class, 'lemari_id', 'lemari_id');
    }

    public function toolbox(): BelongsTo
    {
        return $this->belongsTo(Toolbox::class, 'toolbox_id', 'toolbox_id');
    }
}
