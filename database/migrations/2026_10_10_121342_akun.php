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
        Schema::create('akun', function (Blueprint $table) {
            $table->uuid('akun_id')->primary();
            $table->string('username', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('password_hash');
            $table->enum('role', ['admin', 'laboran', 'peminjam']);
            $table->boolean('must_change_password')->default(0);
            $table->boolean('is_active')->default(1);
            $table->datetime('last_login_at')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akun');
    }
};
