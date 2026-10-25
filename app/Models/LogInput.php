<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogInput extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'log_input';

    protected $primaryKey = 'log_input_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'jenis_input',
        'nama_file',
        'total_baris',
        'baris_berhasil',
        'baris_gagal',
        'status',
        'detail_error',
        'mulai_at',
        'selesai_at',
    ];

    protected function casts(): array
    {
        return [
            'detail_error' => 'array',
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
            'total_baris' => 'integer',
            'baris_berhasil' => 'integer',
            'baris_gagal' => 'integer',
        ];
    }

    public function logMutasiBarangs(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'log_input_id', 'log_input_id');
    }
}
