<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Peminjaman extends Model
{
    use HasFactory, HasUuids;

    public const CANCELLATION_REQUEST_MARKER = '[PEMBATALAN_DIAJUKAN]';

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

    public function hasCancellationRequest(): bool
    {
        return str_contains($this->catatan ?? '', self::CANCELLATION_REQUEST_MARKER);
    }

    public function requestCancellation(): void
    {
        if ($this->hasCancellationRequest()) {
            return;
        }

        $separator = filled($this->catatan) ? "\n" : '';
        $updatedNote = ($this->catatan ?? '').$separator.self::CANCELLATION_REQUEST_MARKER;

        if (Str::length($updatedNote) > 255) {
            throw ValidationException::withMessages([
                'peminjaman' => 'Catatan transaksi terlalu panjang untuk menyimpan permintaan pembatalan. Hubungi admin.',
            ]);
        }

        $this->catatan = $updatedNote;
        $this->save();
    }

    public function clearCancellationRequest(): void
    {
        if (! $this->hasCancellationRequest()) {
            return;
        }

        $this->catatan = trim(str_replace(self::CANCELLATION_REQUEST_MARKER, '', $this->catatan ?? ''));
        $this->save();
    }
}
