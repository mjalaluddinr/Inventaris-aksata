<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peminjaman', function (Blueprint $table) {
            $table->uuid('peminjaman_id')->primary();
            $table->string('kode_peminjaman', 30)->unique('uq_peminjaman_kode');
            $table->string('jenis_peminjaman', 30)->nullable();
            $table->string('keperluan', 255)->nullable();
            $table->timestamp('tanggal_pengajuan')->useCurrent();
            $table->timestamp('tanggal_pinjam')->nullable();
            $table->date('tanggal_rencana_kembali')->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreignUuid('akun_id')->constrained('akun', 'akun_id')->restrictOnDelete();
            $table->foreignUuid('diproses_akun_id')->nullable()->constrained('akun', 'akun_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman');
    }
};
