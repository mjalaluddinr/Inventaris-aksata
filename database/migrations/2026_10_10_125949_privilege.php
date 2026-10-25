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
        Schema::create('privilege', function (Blueprint $table) {
            $table->uuid('privilege_id')->primary();
            $table->foreignUuid('akun_id')
                ->constrained(
                    table: 'akun',
                    column: 'akun_id'
                )
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('hak_akses', 50);
            $table->dateTime('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privilege');
    }
};
