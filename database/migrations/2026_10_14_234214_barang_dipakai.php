<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_dipakai', function (Blueprint $table) {
            $table->uuid('barang_dipakai_id')->primary();
            $table->integer('jumlah');
            $table->string('keterangan', 255)->nullable();
            $table->enum('status', ['aktif', 'ditarik'])->default('aktif');
            $table->timestamp('tanggal_pasang')->useCurrent();
            $table->timestamp('tanggal_tarik')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreignUuid('barang_id')->constrained('barang', 'barang_id')->restrictOnDelete();
            $table->foreignUuid('ruangan_id')->constrained('ruangan', 'ruangan_id')->restrictOnDelete();
            $table->foreignUuid('akun_id')->nullable()->constrained('akun', 'akun_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang_dipakai');
    }
};
