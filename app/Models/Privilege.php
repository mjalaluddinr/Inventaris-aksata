<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Privilege extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'privilege';

    protected $primaryKey = 'privilege_id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'akun_id',
        'hak_akses',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'akun_id', 'akun_id');
    }
}
