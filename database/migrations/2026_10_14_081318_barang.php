<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->uuid('barang_id')->primary();
            $table->string('kode_barang', 30)->unique('uq_barang_kode');
            $table->string('image_path')->nullable(); // Menampung path/nama file foto barang
            $table->string('nama_barang', 150);
            $table->string('satuan', 20)->default('unit');
            $table->integer('stok_total')->default(0);
            $table->integer('stok_tersedia')->default(0);
            $table->enum('kondisi', ['baru', 'lama'])->default('baru');
            $table->enum('kelayakan', ['layak', 'tidak_layak'])->default('layak');
            $table->string('spesifikasi', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();

            $table->foreignUuid('kategori_id')->constrained('kategori', 'kategori_id')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
