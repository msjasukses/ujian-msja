<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin | operator. Guru & siswa tidak disimpan di sini —
            // keduanya login memakai data dari database "datacenter".
            $table->string('role', 20)->default('admin')->after('email');
            $table->boolean('is_aktif')->default(true)->after('role');
            $table->timestamp('last_seen_at')->nullable()->after('is_aktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_aktif', 'last_seen_at']);
        });
    }
};
