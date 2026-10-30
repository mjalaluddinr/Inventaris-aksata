<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Toolbox extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'toolbox';

    protected $primaryKey = 'toolbox_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_toolbox',
        'nama_toolbox',
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

    public function lokasiBarangs(): HasMany
    {
        return $this->hasMany(LokasiBarang::class, 'toolbox_id', 'toolbox_id');
    }
}
