<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Services\PengawasanUjianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penghitungan pelanggaran pengawasan ujian.
 *
 * Yang diuji di sini bukan apakah pelanggaran tercatat — itu bagian yang
 * mudah — melainkan apakah satu perbuatan hanya terhitung satu kali. Sekali
 * berpindah aplikasi membuat Android mengirim beberapa perubahan daur hidup
 * berturut-turut, dan peramban memicu blur hampir bersamaan dengan
 * visibilitychange; tanpa penjagaan, satu kepergian mengunci lembar siswa
 * yang batas pelanggarannya tiga.
 */
class PengawasanPelanggaranTest extends TestCase
{
    use RefreshDatabase;

    protected PengawasanUjianService $pengawasan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pengawasan = app(PengawasanUjianService::class);
    }

    protected function peserta(int $maks = 3): UjianPeserta
    {
        $paket = PaketSoal::create([
            'kode_paket' => 'PGW-1', 'nama_paket' => 'Paket Pengawasan', 'is_aktif' => true,
        ]);

        $ujian = Ujian::create([
            'kode_ujian' => 'PGW-UJN', 'nama_ujian' => 'Ujian Pengawasan',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(5), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
            'proteksi_ketat' => true, 'maks_pelanggaran' => $maks,
        ]);

        // siswa_id tidak berelasi kunci asing ke sini: data siswa tinggal di
        // database datacenter yang dibaca read-only.
        return UjianPeserta::create([
            'ujian_id' => $ujian->id, 'siswa_id' => 1, 'status' => UjianPeserta::MULAI,
        ]);
    }

    /** Beberapa laporan sejenis yang datang beruntun dihitung satu kali. */
    public function test_laporan_kembar_hanya_dihitung_sekali(): void
    {
        $peserta = $this->peserta();

        // Persis yang dikirim aplikasi untuk sekali berpindah aplikasi:
        // hidden, paused, lalu hidden lagi saat kembali.
        for ($i = 0; $i < 3; $i++) {
            $keadaan = $this->pengawasan->catat($peserta, 'keluar_aplikasi');
        }

        $this->assertSame(1, $keadaan['pelanggaran']);
        $this->assertFalse($keadaan['terkunci']);
        $this->assertSame(1, UjianLog::where('ujian_peserta_id', $peserta->id)
            ->where('event', 'keluar_aplikasi')->count());
    }

    /** Jenis yang berbeda pada detik yang sama tetap terhitung sendiri. */
    public function test_jenis_berbeda_tetap_dihitung_terpisah(): void
    {
        $peserta = $this->peserta();

        $this->pengawasan->catat($peserta, 'keluar_aplikasi');
        $keadaan = $this->pengawasan->catat($peserta, 'layar_terbelah');

        $this->assertSame(2, $keadaan['pelanggaran']);
    }

    /** Lewat jedanya, perbuatan yang sama terhitung sebagai pelanggaran baru. */
    public function test_pelanggaran_berulang_setelah_jeda_tetap_dihitung(): void
    {
        $peserta = $this->peserta();

        $this->pengawasan->catat($peserta, 'keluar_aplikasi');

        // Majukan jejaknya ke masa lalu, seolah kepergian pertama sudah lewat.
        UjianLog::where('ujian_peserta_id', $peserta->id)
            ->update(['created_at' => now()->subSeconds(PengawasanUjianService::JEDA_ULANG_DETIK + 1)]);

        $keadaan = $this->pengawasan->catat($peserta, 'keluar_aplikasi');

        $this->assertSame(2, $keadaan['pelanggaran']);
    }

    /** Batasnya tetap berlaku: tiga kepergian terpisah mengunci lembar. */
    public function test_batas_tetap_mengunci_lembar(): void
    {
        $peserta = $this->peserta(maks: 3);

        for ($i = 0; $i < 3; $i++) {
            $keadaan = $this->pengawasan->catat($peserta, 'keluar_aplikasi');

            UjianLog::where('ujian_peserta_id', $peserta->id)
                ->update(['created_at' => now()->subMinute()]);
        }

        $this->assertSame(3, $keadaan['pelanggaran']);
        $this->assertTrue($keadaan['terkunci']);
        $this->assertNotNull($peserta->fresh()->dikunci_at);
    }
}
