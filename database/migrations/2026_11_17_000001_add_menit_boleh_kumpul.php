<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ambang waktu tombol "Selesai & Kumpulkan" pada lembar peserta.
 *
 * Isinya jumlah menit terakhir masa pengerjaan: tombol Selesai baru aktif
 * bila sisa waktu lembar peserta sudah kurang dari atau sama dengan angka
 * ini. Dipasang per ujian — bukan sebagai setelan sekolah di tabel
 * pengaturan — mengikuti aturan pengerjaan lain yang sudah ada di tabel ini
 * (durasi_menit, maks_pelanggaran, wajib_exambro): ulangan harian biasanya
 * boleh dikumpulkan kapan saja, sedangkan ujian sekolah perlu menahan
 * peserta di ruangan sampai mendekati waktu berakhir.
 *
 * Bawaannya 0 yang berarti bebas — ujian yang sudah berjalan tidak berubah
 * perilakunya setelah migrasi ini dijalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            $table->unsignedSmallInteger('menit_boleh_kumpul')->default(0)->after('durasi_menit');
        });
    }

    public function down(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            $table->dropColumn('menit_boleh_kumpul');
        });
    }
};
