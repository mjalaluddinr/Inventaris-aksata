<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarangDipakai extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'barang_dipakai';

    protected $primaryKey = 'barang_dipakai_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'barang_id',
        'ruangan_id',
        'akun_id',
        'jumlah',
        'keterangan',
        'status',
        'tanggal_pasang',
        'tanggal_tarik',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'tanggal_pasang' => 'datetime',
            'tanggal_tarik' => 'datetime',
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

    public function akun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'akun_id', 'akun_id');
    }

    public function logMutasiBarangs(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'barang_dipakai_id', 'barang_dipakai_id');
    }
}
