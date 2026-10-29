<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis soal baru: benar/salah kompleks (bentuk yang dipakai AKM/ANBK).
 *
 * Satu butir memuat beberapa pernyataan, dan peserta menilai tiap pernyataan
 * benar atau salah. Strukturnya disimpan pada kolom JSON yang sudah ada:
 *
 *   opsi  = [{"key":"1","text":"pernyataan"}, {"key":"2","text":"..."}]
 *   kunci = {"1":"benar","2":"salah"}
 *   jawaban peserta (ujian_jawaban.jawaban) = {"1":"benar","2":"salah"}
 *
 * Jadi tidak ada kolom baru — yang perlu dilebarkan hanya kolom "jenis":
 * kode "benar_salah_kompleks" pas 20 karakter, sama dengan batas lamanya,
 * sehingga tidak menyisakan ruang sedikit pun untuk jenis berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soal', function (Blueprint $table) {
            $table->string('jenis', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('soal', function (Blueprint $table) {
            $table->string('jenis', 20)->change();
        });
    }
};
