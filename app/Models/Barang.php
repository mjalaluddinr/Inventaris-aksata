<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Barang extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const SUPABASE_IMAGE_PATH_PREFIX = 'supabase:';

    protected $table = 'barang';

    protected $primaryKey = 'barang_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kategori_id',
        'kode_barang',
        'image_path',
        'nama_barang',
        'satuan',
        'stok_total',
        'stok_tersedia',
        'kondisi',
        'kelayakan',
        'spesifikasi',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'stok_total' => 'integer',
            'stok_tersedia' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Interact with the barang's image url.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            $imagePath = $this->image_path;
            if (! is_string($imagePath)) {
                return null;
            }

            $isSupabaseImage = str_starts_with($imagePath, self::SUPABASE_IMAGE_PATH_PREFIX);
            if ($isSupabaseImage) {
                $imagePath = substr($imagePath, strlen(self::SUPABASE_IMAGE_PATH_PREFIX));
            }

            if (preg_match('/\Abarang_img\/[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)\z/i', $imagePath) !== 1) {
                return null;
            }

            if ($isSupabaseImage) {
                return Storage::disk('supabase')->url($imagePath);
            }

            $pathSegments = explode('/', $imagePath);
            if (in_array('', $pathSegments, true) || in_array('.', $pathSegments, true) || in_array('..', $pathSegments, true)) {
                return null;
            }

            // ASVS 1.2.1/1.2.2: Keep image requests on the page's origin and encode each URL path segment.
            return '/storage/'.implode('/', array_map('rawurlencode', $pathSegments));
        });
    }

    public static function normalizeImageReference(string $filename): string
    {
        $normalized = str_replace('\\', '/', trim($filename));
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;
        $normalized = preg_replace('#^(?:\./)+#', '', $normalized) ?? $normalized;

        return Str::lower($normalized);
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id', 'kategori_id');
    }

    public function lokasiBarangs(): HasMany
    {
        return $this->hasMany(LokasiBarang::class, 'barang_id', 'barang_id');
    }

    public function detailPeminjamans(): HasMany
    {
        return $this->hasMany(DetailPeminjaman::class, 'barang_id', 'barang_id');
    }

    public function barangDipakais(): HasMany
    {
        return $this->hasMany(BarangDipakai::class, 'barang_id', 'barang_id');
    }

    public function logMutasiBarangs(): HasMany
    {
        return $this->hasMany(LogMutasiBarang::class, 'barang_id', 'barang_id');
    }
}
