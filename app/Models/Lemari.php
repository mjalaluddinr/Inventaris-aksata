<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lemari extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lemari';

    protected $primaryKey = 'lemari_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ruangan_id',
        'kode_lemari',
        'nama_lemari',
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

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id', 'ruangan_id');
    }

    public function lokasiBarangs(): HasMany
    {
        return $this->hasMany(LokasiBarang::class, 'lemari_id', 'lemari_id');
    }

    public function logMutasiAsal(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'lemari_asal_id', 'lemari_id');
    }

    public function logMutasiTujuan(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'lemari_tujuan_id', 'lemari_id');
    }
}
