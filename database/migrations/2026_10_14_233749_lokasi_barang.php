<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lokasi_barang', function (Blueprint $table) {
            $table->uuid('lokasi_barang_id')->primary();
            $table->integer('stok_total')->default(0);
            $table->integer('stok_tersedia')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreignUuid('barang_id')->constrained('barang', 'barang_id')->restrictOnDelete();
            $table->foreignUuid('ruangan_id')->constrained('ruangan', 'ruangan_id')->restrictOnDelete();
            $table->foreignUuid('lemari_id')->nullable()->constrained('lemari', 'lemari_id')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lokasi_barang');
    }
};
