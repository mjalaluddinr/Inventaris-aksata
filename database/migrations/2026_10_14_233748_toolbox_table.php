<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('toolbox')) {
            return;
        }

        Schema::create('toolbox', function (Blueprint $table) {
            $table->uuid('toolbox_id')->primary();
            $table->string('kode_toolbox', 20)->unique('uq_toolbox_kode');
            $table->string('nama_toolbox', 100);
            $table->string('keterangan', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('migrations')->where('migration', '2026_10_14_233748_create_toolbox_table')->exists()) {
            return;
        }

        Schema::dropIfExists('toolbox');
    }
};
