<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti aplikasi Ujian (database "ujian").
 *
 * Catatan penting soal relasi lintas database: kolom seperti siswa_id,
 * guru_id, mata_pelajaran_id, tingkat_kelas_id dan rombongan_belajar_id
 * menunjuk ke tabel di database "datacenter" (koneksi terpisah, read-only),
 * jadi sengaja TIDAK dibuat foreign key constraint — MySQL tidak bisa
 * membuat FK lintas database dengan aman bila server datacenter dipisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Topik / materi ujian. Bisa diisi manual atau hasil sinkron dari
        // tabel pemetaan_cp_tp_atp pada database "kurikulum".
        // ------------------------------------------------------------------
        Schema::create('topik', function (Blueprint $table) {
            $table->id();
            $table->string('kode_topik', 40)->nullable();
            $table->string('nama_topik');
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->unsignedBigInteger('tingkat_kelas_id')->nullable()->index();
            $table->string('fase', 10)->nullable();
            $table->string('semester', 20)->nullable();
            $table->string('tahun_ajaran', 30)->nullable()->index();
            $table->text('elemen')->nullable();
            $table->text('capaian_pembelajaran')->nullable();
            $table->text('tujuan_pembelajaran')->nullable();
            $table->text('alur_tujuan_pembelajaran')->nullable();
            $table->text('indikator_kktp')->nullable();
            // 'manual' = diinput dari menu Topik, 'sinkron' = hasil tarik CP-TP-ATP
            $table->string('sumber', 20)->default('manual');
            $table->unsignedBigInteger('sumber_ref_id')->nullable();
            $table->timestamp('disinkron_pada')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['sumber', 'sumber_ref_id'], 'topik_sumber_ref_unique');
        });

        // ------------------------------------------------------------------
        // Bank soal
        // ------------------------------------------------------------------
        Schema::create('soal', function (Blueprint $table) {
            $table->id();
            $table->string('kode_soal', 40)->nullable()->index();
            $table->foreignId('topik_id')->nullable()->constrained('topik')->nullOnDelete();
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->unsignedBigInteger('tingkat_kelas_id')->nullable()->index();
            $table->string('tahun_ajaran', 30)->nullable();
            // pg | pg_kompleks | essay | penjodohan | benar_salah
            $table->string('jenis', 20)->index();
            $table->longText('pertanyaan');
            $table->string('gambar')->nullable();
            /**
             * Struktur opsi & kunci per jenis soal (disimpan JSON):
             *  pg / pg_kompleks : opsi  = [{"key":"A","text":"..."}, ...]
             *                     kunci = ["A"]  atau  ["A","C"]
             *  benar_salah      : opsi  = null
             *                     kunci = ["benar"] atau ["salah"]
             *  penjodohan       : opsi  = {"kiri":[{"key":"1","text":"..."}],
             *                              "kanan":[{"key":"A","text":"..."}]}
             *                     kunci = {"1":"A","2":"C"}
             *  essay            : opsi  = null
             *                     kunci = {"jawaban":"...","kata_kunci":["...","..."]}
             */
            $table->json('opsi')->nullable();
            $table->json('kunci')->nullable();
            $table->decimal('bobot', 6, 2)->default(1);
            $table->string('level_kognitif', 5)->nullable();   // C1..C6
            $table->string('tingkat_kesukaran', 15)->nullable(); // mudah/sedang/sukar
            $table->text('pembahasan')->nullable();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Paket soal = kumpulan soal yang dipilih untuk diujikan
        // ------------------------------------------------------------------
        Schema::create('paket_soal', function (Blueprint $table) {
            $table->id();
            $table->string('kode_paket', 40)->unique();
            $table->string('nama_paket');
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->unsignedBigInteger('tingkat_kelas_id')->nullable()->index();
            $table->string('tahun_ajaran', 30)->nullable();
            $table->string('semester', 20)->nullable();
            $table->string('jenis_ujian', 30)->nullable(); // UH, PTS, PAS, US, Try Out
            $table->text('deskripsi')->nullable();
            $table->boolean('acak_soal')->default(false);
            $table->boolean('acak_opsi')->default(false);
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('paket_soal_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_soal_id')->constrained('paket_soal')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->unsignedSmallInteger('nomor_urut')->default(0);
            $table->decimal('bobot', 6, 2)->default(1);
            $table->timestamps();

            $table->unique(['paket_soal_id', 'soal_id']);
        });

        // ------------------------------------------------------------------
        // Registrasi / jadwal ujian
        // ------------------------------------------------------------------
        Schema::create('ujian', function (Blueprint $table) {
            $table->id();
            $table->string('kode_ujian', 40)->unique();
            $table->string('nama_ujian');
            $table->foreignId('paket_soal_id')->constrained('paket_soal')->cascadeOnDelete();
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->string('tahun_ajaran', 30)->nullable();
            $table->string('semester', 20)->nullable();
            $table->dateTime('waktu_mulai');
            $table->dateTime('waktu_selesai');
            $table->unsignedSmallInteger('durasi_menit')->default(60);
            $table->string('token', 10)->nullable();
            $table->decimal('kkm', 5, 2)->default(75);
            $table->boolean('tampilkan_hasil')->default(true);
            $table->boolean('acak_soal')->default(false);
            $table->boolean('acak_opsi')->default(false);
            // draft | aktif | selesai
            $table->string('status', 20)->default('draft')->index();
            // ujian remidial dibuat dari ujian induk
            $table->foreignId('ujian_induk_id')->nullable()->constrained('ujian')->nullOnDelete();
            $table->boolean('is_remidial')->default(false);
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ujian_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $table->unsignedBigInteger('rombongan_belajar_id')->index();
            $table->timestamps();

            $table->unique(['ujian_id', 'rombongan_belajar_id']);
        });

        Schema::create('ujian_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $table->unsignedBigInteger('siswa_id')->index();
            $table->unsignedBigInteger('rombongan_belajar_id')->nullable()->index();
            $table->string('nomor_peserta', 30)->nullable();
            // terdaftar | mulai | selesai | dibatalkan
            $table->string('status', 20)->default('terdaftar')->index();
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->integer('sisa_detik')->nullable();
            $table->decimal('nilai', 6, 2)->nullable();
            $table->decimal('skor_objektif', 8, 2)->default(0);
            $table->decimal('skor_essay', 8, 2)->default(0);
            $table->unsignedSmallInteger('jumlah_benar')->default(0);
            $table->unsignedSmallInteger('jumlah_salah')->default(0);
            $table->unsignedSmallInteger('jumlah_kosong')->default(0);
            $table->unsignedSmallInteger('reset_count')->default(0);
            $table->boolean('essay_dinilai')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('browser', 60)->nullable();
            $table->timestamps();

            $table->unique(['ujian_id', 'siswa_id']);
        });

        Schema::create('ujian_jawaban', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_peserta_id')->constrained('ujian_peserta')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->unsignedSmallInteger('nomor_urut')->default(0);
            // Bentuk jawaban mengikuti jenis soal, disimpan JSON:
            //  pg/benar_salah -> ["A"] ; pg_kompleks -> ["A","C"]
            //  penjodohan     -> {"1":"A","2":"C"} ; essay -> {"teks":"..."}
            $table->json('jawaban')->nullable();
            // urutan opsi hasil pengacakan supaya tampilan konsisten saat refresh
            $table->json('urutan_opsi')->nullable();
            $table->boolean('ragu')->default(false);
            $table->boolean('is_benar')->nullable();
            $table->decimal('skor', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['ujian_peserta_id', 'soal_id']);
        });

        // Jejak aktivitas peserta selama ujian (untuk menu Monitoring)
        Schema::create('ujian_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $table->foreignId('ujian_peserta_id')->nullable()->constrained('ujian_peserta')->cascadeOnDelete();
            $table->string('event', 40)->index(); // mulai, selesai, blur, reset, login_token, dsb
            $table->string('keterangan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // Tindak lanjut hasil ujian: remidial & pengayaan
        Schema::create('tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $table->unsignedBigInteger('siswa_id')->index();
            $table->string('jenis', 20); // remidial | pengayaan
            $table->decimal('nilai_awal', 6, 2)->nullable();
            $table->decimal('nilai_akhir', 6, 2)->nullable();
            $table->date('tanggal')->nullable();
            $table->string('bentuk')->nullable(); // tes ulang, tugas, tutor sebaya, proyek
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('direncanakan'); // direncanakan|berjalan|selesai
            $table->timestamps();

            $table->unique(['ujian_id', 'siswa_id', 'jenis'], 'tindak_lanjut_unik');
        });

        // Log percobaan login (menu Log Login)
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('username')->index();
            $table->string('guard', 30)->index();
            $table->boolean('success')->default(false)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 30)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('tindak_lanjut');
        Schema::dropIfExists('ujian_log');
        Schema::dropIfExists('ujian_jawaban');
        Schema::dropIfExists('ujian_peserta');
        Schema::dropIfExists('ujian_kelas');
        Schema::dropIfExists('ujian');
        Schema::dropIfExists('paket_soal_detail');
        Schema::dropIfExists('paket_soal');
        Schema::dropIfExists('soal');
        Schema::dropIfExists('topik');
    }
};
