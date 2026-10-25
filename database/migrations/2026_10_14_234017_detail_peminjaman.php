<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_peminjaman', function (Blueprint $table) {
            $table->uuid('detail_peminjaman_id')->primary();
            $table->integer('jumlah_pinjam');
            $table->integer('jumlah_kembali')->default(0);
            $table->enum('status', ['pending', 'dipinjam', 'dikembalikan', 'ditolak', 'dibatalkan'])->default('pending');
            $table->timestamp('tanggal_kembali')->nullable();
            $table->enum('kondisi_kembali', ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])->nullable();
            $table->string('catatan_kembali', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['peminjaman_id', 'barang_id'], 'uq_detail_peminjaman_barang');
            $table->foreignUuid('peminjaman_id')->constrained('peminjaman', 'peminjaman_id')->cascadeOnDelete();
            $table->foreignUuid('barang_id')->constrained('barang', 'barang_id')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_peminjaman');
    }
};
