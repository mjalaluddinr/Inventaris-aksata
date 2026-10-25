<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('log_input', function (Blueprint $table) {
            $table->uuid('log_input_id')->primary();
            $table->enum('jenis_input', ['akun', 'barang']);
            $table->string('nama_file');
            $table->integer('total_baris');
            $table->integer('baris_berhasil');
            $table->integer('baris_gagal');
            $table->enum('status', ['proses', 'selesai', 'sebagian', 'gagal']);
            $table->json('detail_error')->nullable();
            $table->dateTime('mulai_at')->default(NOW());
            $table->dateTime('selesai_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_input');
    }
};
