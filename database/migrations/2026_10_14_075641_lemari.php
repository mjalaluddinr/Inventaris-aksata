<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lemari', function (Blueprint $table) {
            $table->uuid('lemari_id')->primary();
            $table->string('kode_lemari', 20);
            $table->string('nama_lemari', 100);
            $table->string('keterangan', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();

            $table->unique(['ruangan_id', 'kode_lemari'], 'uq_lemari_ruangan_kode');

            // ✅ PERBAIKAN: foreignUuid
            $table->foreignUuid('ruangan_id')->constrained('ruangan', 'ruangan_id')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lemari');
    }
};
