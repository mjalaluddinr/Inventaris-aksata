<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kategori extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'kategori';

    protected $primaryKey = 'kategori_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nama_kategori',
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

    public function barangs(): HasMany
    {
        return $this->hasMany(Barang::class, 'kategori_id', 'kategori_id');
    }
}
