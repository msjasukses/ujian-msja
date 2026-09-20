<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\TindakLanjut;
use App\Models\Topik;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\AnalisisButirService;
use App\Services\StatistikUjianService;
use Database\Seeders\ContohSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeder data contoh harus menghasilkan data yang bukan sekadar ada, tetapi
 * juga masuk akal: sebaran nilai menyerupai satu kelas sungguhan dan analisis
 * butirnya memberi kesimpulan yang berarti. Tes ini menjaga keduanya.
 */
class ContohSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Siswa::query()->exists()) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->seed(ContohSeeder::class);
    }

    public function test_menghasilkan_bank_soal_lengkap_kelima_jenis(): void
    {
        $this->assertGreaterThanOrEqual(8, Topik::count());
        $this->assertGreaterThanOrEqual(40, Soal::count());

        foreach (array_keys(Soal::JENIS) as $jenis) {
            $this->assertTrue(
                Soal::where('jenis', $jenis)->exists(),
                "Bank soal contoh tidak memuat jenis soal \"{$jenis}\"."
            );
        }

        // Tiap butir wajib punya kunci yang bisa dipakai mengoreksi.
        Soal::all()->each(function (Soal $s) {
            $this->assertNotEmpty($s->kunci, "Soal #{$s->id} tidak punya kunci jawaban.");
        });
    }

    public function test_menghasilkan_tiga_jadwal_ujian_dengan_status_berbeda(): void
    {
        $this->assertSame(1, Ujian::where('status', Ujian::SELESAI)->count());
        $this->assertSame(1, Ujian::where('status', Ujian::AKTIF)->count());
        $this->assertSame(1, Ujian::where('status', Ujian::DRAFT)->count());

        // Paket tanpa jadwal disediakan sebagai bahan latihan memilih butir.
        $this->assertTrue(PaketSoal::doesntHave('ujian')->exists());
    }

    /** Ujian yang sudah selesai harus punya sebaran nilai yang wajar. */
    public function test_sebaran_nilai_menyerupai_satu_kelas_sungguhan(): void
    {
        $ujian = Ujian::where('status', Ujian::SELESAI)->firstOrFail();
        $ringkasan = app(StatistikUjianService::class)->untukUjian($ujian)['ringkasan'];

        $this->assertSame(60, $ringkasan['selesai']);

        // Rata-rata di kisaran nilai ujian pada umumnya, bukan menempel di 0 atau 100.
        $this->assertGreaterThan(50, $ringkasan['rata_rata']);
        $this->assertLessThan(85, $ringkasan['rata_rata']);

        // Ada jarak nyata antara nilai tertinggi dan terendah.
        $this->assertGreaterThan(35, $ringkasan['tertinggi'] - $ringkasan['terendah']);
        $this->assertGreaterThan(8, $ringkasan['simpangan_baku']);

        // Kedua kelompok tindak lanjut sama-sama terisi.
        $this->assertGreaterThan(0, $ringkasan['tuntas']);
        $this->assertGreaterThan(0, $ringkasan['belum_tuntas']);

        // Sebagian lembar sengaja menunggu penilaian essay.
        $this->assertGreaterThan(0, $ringkasan['menunggu_koreksi']);
    }

    /** Analisis butir harus memberi kesimpulan, bukan angka acak tanpa pola. */
    public function test_analisis_butir_memberi_kesimpulan_yang_berarti(): void
    {
        $ujian = Ujian::where('status', Ujian::SELESAI)->firstOrFail();
        $analisis = app(AnalisisButirService::class)->untukUjian($ujian);

        $this->assertGreaterThan(10, $analisis['ringkasan']['jumlah_butir']);

        // Sebagian besar butir membedakan siswa pandai dan kurang — daya
        // pembeda rata-ratanya positif dan tidak sekadar mendekati nol.
        $this->assertGreaterThan(0.2, $analisis['ringkasan']['rata_daya_pembeda']);
        $this->assertGreaterThan(0, $analisis['ringkasan']['diterima']);

        // Satu butir sengaja dibuat menyesatkan sebagai bahan peragaan.
        $bermasalah = $analisis['butir']->firstWhere('keputusan', 'Buang / perbaiki kunci');
        $this->assertNotNull($bermasalah, 'Seeder tidak menghasilkan contoh butir dengan daya pembeda negatif.');
        $this->assertLessThan(0, $bermasalah->daya_pembeda);

        // Sebaran pengecoh pada soal pilihan ganda ikut terisi.
        $pg = $analisis['butir']->firstWhere('jenis', Soal::PG);
        $this->assertNotEmpty($pg->pengecoh);
        $this->assertSame(1, collect($pg->pengecoh)->where('kunci', true)->count());
    }

    public function test_menyediakan_data_monitoring_dan_tindak_lanjut(): void
    {
        $berlangsung = Ujian::where('status', Ujian::AKTIF)->firstOrFail();

        $this->assertTrue($berlangsung->sedang_berlangsung);
        $this->assertGreaterThan(0, $berlangsung->peserta()->where('status', UjianPeserta::MULAI)->count());
        $this->assertGreaterThan(0, $berlangsung->peserta()->where('status', UjianPeserta::TERDAFTAR)->count());
        $this->assertGreaterThan(0, $berlangsung->log()->count());

        $this->assertGreaterThan(0, TindakLanjut::where('jenis', TindakLanjut::REMIDIAL)->count());
        $this->assertGreaterThan(0, TindakLanjut::where('jenis', TindakLanjut::PENGAYAAN)->count());
        // Sebagian rencana sudah punya nilai akhir, sebagian masih direncanakan.
        $this->assertGreaterThan(0, TindakLanjut::whereNotNull('nilai_akhir')->count());
        $this->assertGreaterThan(0, TindakLanjut::whereNull('nilai_akhir')->count());
    }

    /** Seluruh halaman laporan terisi data setelah seeding. */
    public function test_halaman_laporan_terisi_setelah_seeding(): void
    {
        $admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);
        $this->actingAs($admin, 'web');

        $selesai = Ujian::where('status', Ujian::SELESAI)->firstOrFail();
        $berlangsung = Ujian::where('status', Ujian::AKTIF)->firstOrFail();

        foreach ([
            "/laporan/nilai/{$selesai->id}",
            "/laporan/statistik/{$selesai->id}",
            "/laporan/analisis-butir/{$selesai->id}",
            "/laporan/remidial/{$selesai->id}",
            "/laporan/pengayaan/{$selesai->id}",
            "/monitoring/{$berlangsung->id}",
            '/log-login',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // Halaman remidial benar-benar memuat daftar siswa, bukan keadaan kosong.
        $this->get("/laporan/remidial/{$selesai->id}")
            ->assertDontSee('Tidak ada siswa yang nilainya di bawah KKM');
        $this->get("/laporan/pengayaan/{$selesai->id}")
            ->assertDontSee('Belum ada siswa yang mencapai KKM');
    }

    /** Seeder aman dijalankan berulang kali tanpa menggandakan data. */
    public function test_aman_dijalankan_berulang(): void
    {
        $sebelum = [
            'topik' => Topik::count(),
            'soal' => Soal::count(),
            'paket' => PaketSoal::count(),
            'ujian' => Ujian::count(),
            'peserta' => UjianPeserta::count(),
        ];

        $this->seed(ContohSeeder::class);

        $this->assertSame($sebelum['topik'], Topik::count());
        $this->assertSame($sebelum['soal'], Soal::count());
        $this->assertSame($sebelum['paket'], PaketSoal::count());
        $this->assertSame($sebelum['ujian'], Ujian::count());
        $this->assertSame($sebelum['peserta'], UjianPeserta::count());
    }
}
