<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_mutasi_barang', function (Blueprint $table) {
            $table->uuid('log_mutasi_barang_id')->primary();
            $table->enum('tipe', ['masuk', 'dipinjam', 'dikembalikan', 'dipakai', 'ditarik', 'dipindahkan', 'penyesuaian']);
            $table->integer('jumlah');
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->string('keterangan', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreignUuid('barang_id')->constrained('barang', 'barang_id')->restrictOnDelete();
            $table->foreignUuid('ruangan_id')->nullable()->constrained('ruangan', 'ruangan_id')->nullOnDelete();
            $table->foreignUuid('lemari_asal_id')->nullable()->constrained('lemari', 'lemari_id')->nullOnDelete();
            $table->foreignUuid('lemari_tujuan_id')->nullable()->constrained('lemari', 'lemari_id')->nullOnDelete();
            $table->foreignUuid('akun_id')->nullable()->constrained('akun', 'akun_id')->nullOnDelete();
            $table->foreignUuid('oleh_akun_id')->nullable()->constrained('akun', 'akun_id')->nullOnDelete();
            $table->foreignUuid('detail_peminjaman_id')->nullable()->constrained('detail_peminjaman', 'detail_peminjaman_id')->nullOnDelete();
            $table->foreignUuid('barang_dipakai_id')->nullable()->constrained('barang_dipakai', 'barang_dipakai_id')->nullOnDelete();
            $table->foreignUuid('log_input_id')->nullable()->constrained('log_input', 'log_input_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_mutasi_barang');
    }
};
